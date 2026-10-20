<?php

namespace App\Actions\Inscriptions;

use App\Models\Participant;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Alta de participante post-onboarding (decisión F-002): crear su enrollment y
// una NUEVA orden de inscripción individual. La orden cubre a ese participante
// y el recálculo retroactivo repricia todas las órdenes de inscripción de la
// familia con el descuento hermanos según el conteo resultante.
class RegisterParticipantInscription
{
    public function __construct(
        private readonly CalculateInscriptionTotal $pricing,
        private readonly RecalculateInscriptionOrders $recalculate,
    ) {}

    public function handle(Participant $participant): void
    {
        $plan = Plan::operative()->latest('starts_at')->first();
        if (! $plan) {
            return;
        }

        DB::transaction(function () use ($participant, $plan): void {
            // F-059 (api-B-2): el representante se bloquea PRIMERO. Dos altas
            // simultaneas de la misma familia quedan en serie (la segunda ve
            // la orden que creo la primera) y el orden de locks es siempre
            // User -> orders (RecalculateInscriptionOrders), el mismo que
            // CompleteOnboarding. Asi no hay ciclos de espera entre ellas.
            $user = User::where('uuid', $participant->user_uuid)->lockForUpdate()->firstOrFail();

            $this->ensureEnrollment($participant, $plan);
            $this->ensureOrderCoversFamily($user, $plan);
            $this->recalculate->handle($user);
        });
    }

    private function ensureEnrollment(Participant $participant, Plan $plan): void
    {
        $participant->enrollment()->updateOrCreate([], [
            'plan_uuid' => $plan->uuid,
            'plan_name' => $plan->name,
            'session_uuid' => (string) Str::uuid(),
            'session_name' => trim(($plan->starts_at?->format('Y-m-d') ?? '').' - '.($plan->ends_at?->format('Y-m-d') ?? '')),
            'status' => 'PENDIENTE_PAGO',
            'plan_type' => 'INDIVIDUAL',
            'total_amount' => (float) $plan->price,
            'sibling_discount' => 0,
            'payment_method' => ['tipo' => 'COMPLETO', 'depositoInicial' => 0],
            'starts_at' => $plan->starts_at ?? now()->toDateString(),
            'ends_at' => $plan->ends_at ?? now()->toDateString(),
        ]);
    }

    private function ensureOrderCoversFamily(User $user, Plan $plan): void
    {
        // F-059 (api-A-5): las plazas de una orden CANCELADA no cuentan como
        // cubiertas (antes un hijo nuevo quedaba inscrito sin orden).
        $covered = (int) $user->orders()
            ->where('is_registration', true)
            ->where('status', '!=', 'CANCELADA')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->reduce(function (int $carry, $order): int {
                $covered = 0;
                foreach (($order->items ?? []) as $item) {
                    if ((float) ($item['precio'] ?? 0) >= 0) {
                        $covered += (int) ($item['qty'] ?? 0);
                    }
                }

                return $carry + $covered;
            }, 0);

        if ($covered >= $user->participants()->count()) {
            return;
        }

        $sessionName = trim(($plan->starts_at?->format('Y-m-d') ?? '').' - '.($plan->ends_at?->format('Y-m-d') ?? ''));
        $user->orders()->create([
            'user_uuid' => $user->uuid,
            'ordered_at' => now()->toDateString(),
            'items' => [[
                'nombre' => 'Inscripción · '.$plan->name,
                'variante' => $sessionName ?: 'Sesión seleccionada',
                'qty' => 1,
                'precio' => (float) $plan->price,
            ]],
            'total' => (float) $plan->price,
            'paid' => 0,
            'status' => 'PENDIENTE_PAGO',
            'is_registration' => true,
        ]);
    }
}