<?php

namespace App\Actions\Onboarding;

use App\Actions\Inscriptions\CalculateInscriptionTotal;
use App\Actions\Inscriptions\RegisterParticipantInscription;
use App\Actions\Payments\LinkOrderPayment;
use App\Models\OnboardingDraft;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompleteOnboarding
{
    public const ALREADY_COMPLETED_MESSAGE = 'Tu inscripción ya fue completada. Para agregar otro participante usa «Agregar otro» en «Mis participantes» del portal.';

    /**
     * F-035: `$receipt` es el comprobante del primer pago (multipart
     * `comprobante` en `POST /onboarding/{id}/complete`). Es OBLIGATORIO
     * cuando hay un monto a reportar y el metodo es DIRECTO (todo lo que no
     * sea COORDINADO_REMOTO); se guarda en el disco privado `local`, igual
     * que `PaymentController::store`, y queda en `receipt_path` del Payment.
     */
    public function handle(OnboardingDraft $draft, ?UploadedFile $receipt = null): OnboardingDraft
    {
        $storedReceiptPath = null;

        try {
            $this->complete($draft, $receipt, $storedReceiptPath);
        } catch (\Throwable $exception) {
            // La transaccion hizo rollback: no dejar archivos huerfanos.
            if ($storedReceiptPath !== null) {
                Storage::disk('local')->delete($storedReceiptPath);
            }

            throw $exception;
        }

        return $draft->refresh();
    }

    private function complete(OnboardingDraft $draft, ?UploadedFile $receipt, ?string &$storedReceiptPath): void
    {
        DB::transaction(function () use ($draft, $receipt, &$storedReceiptPath): void {
            // F-059 (api-B-1): se bloquean el representante y luego el draft
            // (mismo orden que RegisterParticipantInscription: User primero).
            // Dos `complete` simultaneos del mismo usuario quedan en serie y
            // el segundo ve el draft ya COMPLETADO.
            $user = User::where('uuid', $draft->user_uuid)->lockForUpdate()->firstOrFail();
            $draft = OnboardingDraft::whereKey($draft->getKey())->lockForUpdate()->firstOrFail();

            // F-059 (api-A-3): la regla se basa en el estado del
            // REPRESENTANTE. Mientras no haya completado su onboarding, su
            // draft se completa (aunque venga en formato viejo o marcado
            // COMPLETADO sin que el usuario lo este). Una vez COMPLETADO:
            //  - el mismo draft COMPLETADO -> respuesta idempotente (200 con
            //    el draft tal cual, sin crear nada): doble clic o reintento
            //    tras un error de red;
            //  - cualquier otro draft -> 422 (inscribia hijos sin orden que
            //    los cubra). Los hijos nuevos van por POST /participantes
            //    (RegisterParticipantInscription).
            if ($user->onboarding_status === 'COMPLETADO') {
                if ($draft->status === 'COMPLETADO') {
                    return;
                }

                throw ValidationException::withMessages([
                    'onboarding' => [self::ALREADY_COMPLETED_MESSAGE],
                ]);
            }

            $required = ['cuenta', 'participantes', 'pago', 'adicionales', 'confirmacion'];
            $completed = $draft->completed_steps ?? [];
            $missing = array_values(array_diff($required, $completed));

            if ($missing !== []) {
                // fe-B-5: el mensaje explica que hacer (el portal, ademas,
                // reenvia los pasos que tiene guardados y reintenta).
                throw ValidationException::withMessages([
                    'steps' => ['Faltan pasos por guardar ('.implode(', ', $missing).'). Revisa los pasos con «Atrás» y vuelve a confirmar.'],
                ]);
            }

            $participants = $draft->data['participantes']['participantes'] ?? [];
            $accountData = $draft->data['cuenta'] ?? [];
            $planId = (string) ($accountData['planId'] ?? '');
            $paymentData = $draft->data['pago']['pago'] ?? $draft->data['pago'] ?? [];
            $plan = $planId !== ''
                ? Plan::where('uuid', $planId)->operative()->first()
                : null;
            $validParticipants = [];

            foreach ($participants as $participant) {
                if (! is_array($participant) || empty($participant['nombre']) || empty($participant['nacimiento'])) {
                    continue;
                }

                $exists = $user->participants()
                    ->where('name', $participant['nombre'])
                    ->whereDate('birth_date', $participant['nacimiento'])
                    ->exists();

                $model = $exists
                    ? $user->participants()
                        ->where('name', $participant['nombre'])
                        ->whereDate('birth_date', $participant['nacimiento'])
                        ->first()
                    : $user->participants()->create([
                        'name' => $participant['nombre'],
                        'birth_date' => $participant['nacimiento'],
                        'gender' => 'PREFIERO_NO_DECIR',
                        'data_completed' => false,
                        'health' => [],
                        'emergency_contacts' => [],
                        'pickup_contact' => null,
                        'wizard_steps' => [],
                    ]);

                if ($model) {
                    $validParticipants[] = $model;
                }
            }

            if ($validParticipants !== []) {
                if (! $plan) {
                    throw ValidationException::withMessages([
                        'cuenta.planId' => ['El plan seleccionado no es valido.'],
                    ]);
                }
                $count = count($validParticipants);
                $unitPrice = (float) $plan->price;
                $quote = app(CalculateInscriptionTotal::class)->handle($count, $unitPrice, $plan);
                $modality = $paymentData['modalidad'] ?? 'completo';
                $reportedAmount = $modality === 'cuotas'
                    ? (float) ($paymentData['montoAbonar'] ?? 0)
                    : $quote['total'];

                // F-059 (api-B-5): el minimo del abono en cuotas tambien se
                // valida en el servidor (antes solo el cliente). Mismo minimo
                // que enlazar-pago; si el total es menor, basta con el total.
                if ($modality === 'cuotas') {
                    $minimum = min((float) LinkOrderPayment::MINIMUM_INSTALLMENT, (float) $quote['total']);
                    if ($reportedAmount < $minimum) {
                        throw ValidationException::withMessages([
                            'montoAbonar' => ['El abono mínimo es '.Money::format($minimum).'. Vuelve al paso Pago y ajusta el monto.'],
                        ]);
                    }
                }
                $planName = $plan->name;
                $sessionName = trim(($plan->starts_at?->format('Y-m-d') ?? '').' - '.($plan->ends_at?->format('Y-m-d') ?? ''));
                // F-026: `paymentData['metodo']` ya no es un nombre visible
                // hardcodeado ('Zelle'/'Efectivo'/...) sino el `code` real del
                // método elegido en el paso Pago del wizard (ej.
                // "met_zelle"), el mismo catálogo que /portal/pagos consume
                // vía PaymentController::methods(). Se busca el PaymentMethod
                // real, igual que ya hace PaymentController::store(); si el
                // code no calza con ningún método activo (draft viejo,
                // método desactivado entre medio, o paso "Pago" nunca
                // guardado), se cae al primer método activo disponible.
                $methodCodeInput = $paymentData['metodo'] ?? null;
                $paymentMethod = $methodCodeInput
                    ? PaymentMethod::where('code', $methodCodeInput)->where('active', true)->first()
                    : null;
                $paymentMethod ??= PaymentMethod::where('active', true)->first();
                $methodCode = $paymentMethod?->code ?? 'met_zelle';
                $methodName = $paymentMethod?->name ?? 'Zelle';
                $items = [[
                    'nombre' => 'Inscripción · '.$planName,
                    'variante' => $sessionName,
                    'qty' => $count,
                    'precio' => $unitPrice,
                ]];
                if ($quote['descuentoTotal'] > 0) {
                    $items[] = [
                        'nombre' => 'Descuento hermanos',
                        'variante' => $sessionName ?: 'Sesión seleccionada',
                        'qty' => 1,
                        'precio' => -$quote['descuentoTotal'],
                    ];
                }
                // F-059 (api-A-5): una orden de inscripcion CANCELADA no se
                // reutiliza (no cubre plazas ni recibe el primer pago).
                $registrationOrder = $user->orders()
                    ->where('is_registration', true)
                    ->where('status', '!=', 'CANCELADA')
                    ->first();
                $registrationOrderWasNew = $registrationOrder === null;

                if ($registrationOrder) {
                    // F-062 (seguimiento ver_F-059 obs. 1): la familia ya tiene
                    // una orden de inscripcion activa (p. ej. un admin le
                    // devolvio onboarding_status=INCOMPLETO). Esa orden no se
                    // re-precia ni suma plazas aqui:
                    //  - las inscripciones existentes NO se tocan (antes
                    //    `updateOrCreate` las devolvia a PENDIENTE_PAGO);
                    //  - cada hijo sin inscripcion va por el alta normal
                    //    (RegisterParticipantInscription), que le crea su
                    //    plaza/orden y aplica el recalculo F-002.
                    foreach ($validParticipants as $participant) {
                        if (! $participant->enrollment()->exists()) {
                            app(RegisterParticipantInscription::class)->handle($participant);
                        }
                    }
                } else {
                    $registrationOrder = $user->orders()->create([
                        'user_uuid' => $draft->user_uuid,
                        'ordered_at' => now()->toDateString(),
                        'items' => $items,
                        'total' => $quote['total'],
                        'paid' => 0,
                        'status' => 'PENDIENTE_PAGO',
                        'is_registration' => true,
                    ]);
                }

                foreach (($registrationOrderWasNew ? $validParticipants : []) as $participant) {
                    $participant->enrollment()->updateOrCreate([], [
                        'plan_uuid' => $plan?->uuid,
                        'plan_name' => $planName,
                        'session_uuid' => (string) Str::uuid(),
                        'session_name' => $sessionName ?: 'Sesión seleccionada',
                        'status' => 'PENDIENTE_PAGO',
                        'plan_type' => $quote['descuentoAplica'] ? 'HERMANOS' : 'INDIVIDUAL',
                        'total_amount' => $unitPrice,
                        'sibling_discount' => $quote['montoDescuentoPorParticipante'],
                        'payment_method' => [
                            'tipo' => strtoupper($modality),
                            'depositoInicial' => $reportedAmount,
                        ],
                        'starts_at' => $plan?->starts_at ?? now()->toDateString(),
                        'ends_at' => $plan?->ends_at ?? now()->toDateString(),
                    ]);
                }

                // F-023: un método COORDINADO_REMOTO (el primer pago se
                // coordina fuera de la app, ej. WhatsApp) igual crea el
                // Payment -- sin referencia ni comprobante, solo la fila que
                // registra que quedó pendiente de coordinar.
                // F-035: un método DIRECTO exige el comprobante del primer
                // pago (antes bastaba la referencia y el botón del portal no
                // subía nada). Sin comprobante -> 422 `errors.comprobante`.
                // F-052 (A-3): una referencia hecha solo de espacios no cuenta.
                $reference = trim((string) ($paymentData['referencia'] ?? ''));
                $hasReference = $reference !== '';
                $isCoordinatedRemote = $paymentMethod?->type === 'COORDINADO_REMOTO';
                $idempotencyHash = hash('sha256', 'onboarding|'.$draft->id);

                if ($reportedAmount > 0 && ! $isCoordinatedRemote) {
                    $alreadyReported = Payment::where('idempotency_hash', $idempotencyHash)->exists();

                    if (! $alreadyReported) {
                        // F-052 (A-3): un método DIRECTO exige referencia y
                        // comprobante en el servidor (antes solo el cliente
                        // exigía la referencia). Se informan ambos a la vez.
                        $errors = [];
                        if (! $hasReference) {
                            $errors['referencia'] = ['Ingresa el número de referencia del pago.'];
                        }
                        if (! $receipt) {
                            $errors['comprobante'] = ['Adjunta el comprobante de tu primer pago para completar la inscripción.'];
                        }
                        if ($errors !== []) {
                            throw ValidationException::withMessages($errors);
                        }

                        $storedReceiptPath = $receipt->store('comprobantes', 'local');

                        Payment::create([
                            'user_uuid' => $draft->user_uuid,
                            'paid_at' => now()->toDateString(),
                            'amount' => min($reportedAmount, $quote['total']),
                            'currency' => 'USD',
                            'method_code' => $methodCode,
                            'method_name' => $methodName,
                            'reference' => $reference,
                            'concept' => 'Inscripción - '.$planName,
                            'status' => 'PENDIENTE_VERIFICACION',
                            'receipt_path' => $storedReceiptPath,
                            'receipt_name' => $receipt->getClientOriginalName(),
                            'idempotency_hash' => $idempotencyHash,
                            'order_uuid' => $registrationOrder->uuid,
                        ]);
                    }
                } elseif ($reportedAmount > 0) {
                    Payment::firstOrCreate(
                        ['idempotency_hash' => $idempotencyHash],
                        [
                            'user_uuid' => $draft->user_uuid,
                            'paid_at' => now()->toDateString(),
                            'amount' => min($reportedAmount, $quote['total']),
                            'currency' => 'USD',
                            'method_code' => $methodCode,
                            'method_name' => $methodName,
                            'reference' => null,
                            'concept' => 'Inscripción - '.$planName,
                            'status' => 'PENDIENTE_VERIFICACION',
                            'receipt_name' => 'Pago coordinado por WhatsApp',
                            'order_uuid' => $registrationOrder->uuid,
                        ],
                    );
                }
            }

            $draft->update([
                'status' => 'COMPLETADO',
                'version' => $draft->version + 1,
            ]);
            $user->update(['onboarding_status' => 'COMPLETADO']);
        });
    }
}
