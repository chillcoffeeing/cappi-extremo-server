<?php

namespace App\Actions\Inscriptions;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Recálculo retroactivo (decisión F-002): al crecer la familia, TODAS las
// órdenes de inscripción previas se re-precian con el descuento hermanos según
// el nuevo conteo. Las órdenes de tienda no se tocan. El paid se conserva; si
// el total baja, el saldo se recalcula y el excedente queda como crédito (no
// se borra historial de pagos).
//
// F-017: el descuento se lee del plan operativo vigente (mismo criterio que
// RegisterParticipantInscription/ConfigController: Plan::operative()->latest
// ('starts_at')->first()), no de platform_settings. El dominio asume una
// única temporada operativa a la vez, así que toda la familia se re-precia
// con la config de ESE plan.
class RecalculateInscriptionOrders
{
    public function handle(User $user): void
    {
        // F-059 (api-B-2): las ordenes se leen bloqueadas (en orden de id)
        // para que un ApprovePayment concurrente no quede pisado con el `paid`
        // viejo. Los llamadores (RegisterParticipantInscription,
        // DeleteParticipant) ya abrieron la transaccion y bloquearon al User
        // antes; si se llama suelta, esta transaccion la abre aqui.
        DB::transaction(fn () => $this->recalculate($user));
    }

    private function recalculate(User $user): void
    {
        // F-059 (api-A-5): una orden CANCELADA no se re-precia: conserva sus
        // items, total y estado tal como quedaron al cancelarla.
        $orders = $user->orders()
            ->where('is_registration', true)
            ->where('status', '!=', 'CANCELADA')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        if ($orders->isEmpty()) {
            $this->syncEnrollments($user);

            return;
        }

        $familyCount = $user->participants()->count();
        $plan = Plan::operative()->latest('starts_at')->first();
        $discountApplies = $plan
            && $plan->sibling_discount_enabled
            && $familyCount >= $plan->sibling_discount_min_participants;
        $discountPerParticipant = $discountApplies ? (float) $plan->sibling_discount_amount : 0.0;

        foreach ($orders as $order) {
            $items = $order->items ?? [];
            $baseItems = array_values(array_filter($items, fn (array $item): bool => (float) ($item['precio'] ?? 0) >= 0));
            $covered = (int) array_sum(array_column($baseItems, 'qty'));
            $unitPrice = (float) ($baseItems[0]['precio'] ?? 0);
            $baseTotal = round($unitPrice * $covered, 4);
            $totalDiscount = round($discountPerParticipant * $covered, 4);
            $total = max($baseTotal - $totalDiscount, 0.0);

            $discountItems = array_values(array_filter($items, fn (array $item): bool => (float) ($item['precio'] ?? 0) < 0));
            if (! $discountApplies && $discountItems !== []) {
                $items = $baseItems;
            } elseif ($discountApplies) {
                $items = [...$baseItems, [
                    'nombre' => 'Descuento hermanos',
                    'variante' => $discountItems[0]['variante'] ?? $baseItems[0]['variante'] ?? '',
                    'qty' => 1,
                    'precio' => -$totalDiscount,
                ]];
            }

            // F-054 (bug fix): el saldo es total - abonado. Antes estaba
            // invertido (abonado - total), y una orden sin pagos quedaba PAGADA
            // al re-preciarse. Las CANCELADA ya no llegan aqui (F-059).
            $saldo = round($total - (float) $order->paid, 4);

            $order->update([
                'items' => $items,
                'total' => $total,
                'status' => $saldo > 0 ? 'PENDIENTE_PAGO' : 'PAGADA',
            ]);
        }

        $this->syncEnrollments($user);
    }

    private function syncEnrollments(User $user): void
    {
        // Enrollments: el tipo de plan (HERMANOS/INDIVIDUAL) y el descuento por
        // participante son consistentes con la regla del plan operativo vigente.
        $familyCount = $user->participants()->count();
        $plan = Plan::operative()->latest('starts_at')->first();
        $discountApplies = $plan
            && $plan->sibling_discount_enabled
            && $familyCount >= $plan->sibling_discount_min_participants;
        $discountPerParticipant = $discountApplies ? (float) $plan->sibling_discount_amount : 0.0;

        $user->participants()->each(function ($participant) use ($discountApplies, $discountPerParticipant): void {
            $participant->enrollment()->update([
                'plan_type' => $discountApplies ? 'HERMANOS' : 'INDIVIDUAL',
                'sibling_discount' => $discountPerParticipant,
            ]);
        });
    }
}
