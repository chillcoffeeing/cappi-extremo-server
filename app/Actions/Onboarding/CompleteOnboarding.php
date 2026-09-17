<?php

namespace App\Actions\Onboarding;

use App\Models\OnboardingDraft;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Participant;
use App\Models\Plan;
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
            $planData = $accountData['plan'] ?? [];
            $paymentData = $draft->data['pago']['pago'] ?? $draft->data['pago'] ?? [];
            $plan = Plan::where('name', $planData['nombre'] ?? '')->first();
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
                        'slug' => str($participant['nombre'])->slug()->toString(),
                        'birth_date' => $participant['nacimiento'],
                        'data_completed' => false,
                        'health' => [],
                        'emergency_contacts' => [],
                        'pickup_contact' => null,
                        'medical_insurance' => [],
                        'authorizations' => [],
                        'wizard_steps' => [],
                    ]);

                if ($model) {
                    $validParticipants[] = $model;
                }
            }

            if ($validParticipants !== []) {
                $total = (float) ($planData['precio'] ?? 0) * count($validParticipants);
                $modality = $paymentData['modalidad'] ?? 'completo';
                $reportedAmount = $modality === 'cuotas'
                    ? (float) ($paymentData['montoAbonar'] ?? 0)
                    : $total;
                $planName = $planData['nombre'] ?? 'Inscripción al plan';
                $sessionName = trim(($planData['fechaInicio'] ?? '').' - '.($planData['fechaFin'] ?? ''));
                $paymentMethod = $paymentData['metodo'] ?? 'Zelle';
                $methodCode = match ($paymentMethod) {
                    'Efectivo' => 'met_efectivo',
                    'Transferencia', 'Bolívares' => 'met_transferencia_bs',
                    default => 'met_zelle',
                };
                $registrationOrder = $draft->user->orders()
                    ->where('is_registration', true)
                    ->first();

                if (! $registrationOrder) {
                    $registrationOrder = $draft->user->orders()->create([
                        'order_code' => 'ord_insc_'.Str::lower(Str::random(12)),
                        'ordered_at' => now()->toDateString(),
                        'items' => [[
                            'nombre' => 'Inscripción · '.$planName,
                            'variante' => $sessionName,
                            'qty' => count($validParticipants),
                            'precio' => (float) ($planData['precio'] ?? 0),
                        ]],
                        'total' => $total,
                        'paid' => 0,
                        'status' => 'PENDIENTE_PAGO',
                        'is_registration' => true,
                    ]);
                }

                foreach ($validParticipants as $participant) {
                    $participant->enrollment()->updateOrCreate([], [
                        'plan_id' => $plan?->id,
                        'plan_name' => $planName,
                        'session_id' => 'sesion_001',
                        'session_name' => $sessionName ?: 'Sesión seleccionada',
                        'status' => 'PENDIENTE_PAGO',
                        'plan_type' => count($validParticipants) > 1 ? 'HERMANOS' : 'INDIVIDUAL',
                        'total_amount' => (float) ($planData['precio'] ?? 0),
                        'sibling_discount' => 0,
                        'payment_method' => [
                            'tipo' => strtoupper($modality),
                            'depositoInicial' => $reportedAmount,
                        ],
                        'starts_at' => $plan?->starts_at ?? now()->toDateString(),
                        'ends_at' => $plan?->ends_at ?? now()->toDateString(),
                    ]);
                }

                if ($reportedAmount > 0 && ! empty($paymentData['referencia'])) {
                    Payment::firstOrCreate(
                        ['idempotency_hash' => hash('sha256', 'onboarding|'.$draft->id)],
                        [
                            'user_id' => $draft->user_id,
                            'paid_at' => now()->toDateString(),
                            'amount' => min($reportedAmount, $total),
                            'currency' => 'USD',
                            'method_code' => $methodCode,
                            'method_name' => $paymentMethod,
                            'reference' => $paymentData['referencia'],
                            'concept' => 'Inscripción - '.$planName,
                            'status' => 'PENDIENTE_VERIFICACION',
                            'receipt_name' => 'Referencia de onboarding',
                            'order_id' => $registrationOrder->order_code,
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
