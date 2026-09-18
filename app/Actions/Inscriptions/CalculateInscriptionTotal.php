<?php

namespace App\Actions\Inscriptions;

use App\Models\Plan;

// Regla de pricing de inscripción (F-002): el coste por participante es el
// precio del plan; el descuento hermanos se activa cuando el total de
// participantes de la familia alcanza el mínimo configurado y reduce el monto
// por cada participante. Fuente única para onboarding y altas posteriores.
//
// F-017: el descuento (enabled/minimo/monto) se lee del Plan operativo, no de
// la fila global platform_settings -- cada plan configura el suyo.
class CalculateInscriptionTotal
{
    /** @return array<string, float|int|bool> */
    public function handle(int $participants, float $unitPrice, Plan $plan): array
    {
        $discountApplies = $plan->sibling_discount_enabled
            && $participants >= $plan->sibling_discount_min_participants;
        $discountPerParticipant = $discountApplies ? (float) $plan->sibling_discount_amount : 0.0;
        $totalDiscount = round($discountPerParticipant * $participants, 4);
        $total = max(round(($unitPrice * $participants) - $totalDiscount, 4), 0.0);

        return [
            'participantes' => $participants,
            'precioUnidad' => round($unitPrice, 4),
            'descuentoAplica' => $discountApplies,
            'montoDescuentoPorParticipante' => round($discountPerParticipant, 4),
            'descuentoTotal' => $totalDiscount,
            'total' => $total,
        ];
    }

    /** Aplica la regla para UNA alta individual dentro de una familia. */
    public function forParticipant(int $familyParticipants, float $unitPrice, Plan $plan): array
    {
        $quote = $this->handle($familyParticipants, $unitPrice, $plan);

        return [
            'descuentoAplica' => $quote['descuentoAplica'],
            'montoDescuentoPorParticipante' => $quote['montoDescuentoPorParticipante'],
            'total' => max(round($unitPrice - (float) $quote['montoDescuentoPorParticipante'], 4), 0.0),
        ];
    }
}