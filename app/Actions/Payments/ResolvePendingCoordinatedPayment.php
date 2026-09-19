<?php

namespace App\Actions\Payments;

use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\User;

/**
 * F-023: el banner rojo claro de "Inicio" (y el de la pantalla de éxito del
 * onboarding) se activan leyendo el método de pago del `Payment` de la
 * PRIMERA orden de inscripción de la familia (`orders.is_registration =
 * true`, la más antigua). Si ese método es COORDINADO_REMOTO y el pago
 * sigue PENDIENTE_VERIFICACION, se devuelven los textos/link configurados
 * en el método para pintar el banner; en cualquier otro caso, null.
 */
class ResolvePendingCoordinatedPayment
{
    /** @return array<string, string|null>|null */
    public function handle(User $user): ?array
    {
        $registrationOrder = $user->orders()
            ->where('is_registration', true)
            ->oldest('id')
            ->first();

        if (! $registrationOrder) {
            return null;
        }

        $payment = $registrationOrder->payments()->oldest('id')->first();

        if (! $payment || $payment->status !== 'PENDIENTE_VERIFICACION') {
            return null;
        }

        $method = PaymentMethod::where('code', $payment->method_code)->first();

        if (! $method || $method->type !== 'COORDINADO_REMOTO') {
            return null;
        }

        $whatsapp = $method->data['whatsapp'] ?? [];
        $link = $whatsapp['link'] ?? null;

        if (empty($link)) {
            $plan = Plan::operative()->latest('starts_at')->first();
            $link = $plan?->whatsapp ? "https://wa.me/{$plan->whatsapp}" : null;
        }

        return [
            'metodoNombre' => $method->name,
            'mensajeOnboarding' => $whatsapp['mensajeOnboarding'] ?? '',
            'mensajeDashboard' => $whatsapp['mensajeDashboard'] ?? '',
            'linkTexto' => $whatsapp['linkTexto'] ?? '',
            'link' => $link,
        ];
    }
}
