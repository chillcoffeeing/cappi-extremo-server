<?php

namespace App\Actions\Onboarding;

use App\Actions\Inscriptions\CalculateInscriptionTotal;
use App\Models\OnboardingDraft;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Participant;
use App\Models\Plan;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class CompleteOnboarding
{
    public function handle(OnboardingDraft $draft): OnboardingDraft
    {
        $required = ['cuenta', 'participantes', 'pago', 'adicionales', 'confirmacion'];
        $completed = $draft->completed_steps ?? [];
        $missing = array_values(array_diff($required, $completed));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'steps' => ['Faltan pasos: '.implode(', ', $missing)],
            ]);
        }

        DB::transaction(function () use ($draft): void {
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

                $exists = $draft->user->participants()
                    ->where('name', $participant['nombre'])
                    ->whereDate('birth_date', $participant['nacimiento'])
                    ->exists();

                $model = $exists
                    ? $draft->user->participants()
                        ->where('name', $participant['nombre'])
                        ->whereDate('birth_date', $participant['nacimiento'])
                        ->first()
                    : $draft->user->participants()->create([
                        'name' => $participant['nombre'],
                        'birth_date' => $participant['nacimiento'],
                        'gender' => 'PREFIERO_NO_DECIR',
                        'data_completed' => false,
                        'health' => [],
                        'emergency_contacts' => [],
                        'pickup_contact' => null,
                        'medical_insurance' => [],
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
                $registrationOrder = $draft->user->orders()
                    ->where('is_registration', true)
                    ->first();

                if (! $registrationOrder) {
                    $registrationOrder = $draft->user->orders()->create([
                        'user_uuid' => $draft->user_uuid,
                        'ordered_at' => now()->toDateString(),
                        'items' => $items,
                        'total' => $quote['total'],
                        'paid' => 0,
                        'status' => 'PENDIENTE_PAGO',
                        'is_registration' => true,
                    ]);
                }

                foreach ($validParticipants as $participant) {
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
                // registra que quedó pendiente de coordinar. Un método
                // DIRECTO conserva el comportamiento previo: requiere que el
                // paso "Pago" haya guardado una referencia real.
                $hasReference = ! empty($paymentData['referencia']);
                $isCoordinatedRemote = $paymentMethod?->type === 'COORDINADO_REMOTO';

                if ($reportedAmount > 0 && ($hasReference || $isCoordinatedRemote)) {
                    Payment::firstOrCreate(
                        ['idempotency_hash' => hash('sha256', 'onboarding|'.$draft->id)],
                        [
                            'user_uuid' => $draft->user_uuid,
                            'paid_at' => now()->toDateString(),
                            'amount' => min($reportedAmount, $quote['total']),
                            'currency' => 'USD',
                            'method_code' => $methodCode,
                            'method_name' => $methodName,
                            'reference' => $hasReference ? $paymentData['referencia'] : null,
                            'concept' => 'Inscripción - '.$planName,
                            'status' => 'PENDIENTE_VERIFICACION',
                            'receipt_name' => $hasReference ? 'Referencia de onboarding' : 'Pago coordinado por WhatsApp',
                            'order_uuid' => $registrationOrder->uuid,
                        ],
                    );
                }
            }

            $draft->update([
                'status' => 'COMPLETADO',
                'version' => $draft->version + 1,
            ]);
            $draft->user()->update(['onboarding_status' => 'COMPLETADO']);
        });

        return $draft->refresh();
    }
}
