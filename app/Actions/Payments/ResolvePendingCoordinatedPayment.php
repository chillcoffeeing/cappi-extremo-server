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
 * sigue PENDIENTE_VERIFICACION sin comprobante (F-052 B-1), se devuelven los textos/link configurados
 * en el método para pintar el banner; en cualquier otro caso, null.
 */
class ResolvePendingCoordinatedPayment
{
    /** @return array<string, string|float|null>|null */
    public function handle(User $user): ?array
    {
        $registrationOrder = $user->orders()
            ->where('is_registration', true)
            ->oldest('id')
            ->first();

        if (! $registrationOrder) {
            return null;
        }

        // F-052 (B-1): solo el placeholder del onboarding activa el banner:
        // el Payment COORDINADO_REMOTO que CompleteOnboarding crea SIN
        // comprobante ni referencia. Un reporte hecho desde el portal
        // (enlazar-pago / POST /pagos) siempre trae ambos, aunque use un
        // método COORDINADO_REMOTO, así que nunca cuenta. Cuando el
        // placeholder se completa con comprobante (F-051,
        // CompleteCoordinatedPayment), el banner se apaga.
        $payment = $registrationOrder->payments()
            ->where('status', 'PENDIENTE_VERIFICACION')
            ->whereNull('receipt_path')
            ->whereNull('reference')
            ->oldest('id')
            ->first();

        if (! $payment) {
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
            // F-051: el portal abre el modal de pago con la orden de
            // inscripcion preseleccionada y completa ESTE pago (no crea otro).
            'ordenId' => $registrationOrder->uuid,
            'pagoId' => $payment->uuid,
            'monto' => (float) $payment->amount,
            'metodoNombre' => $method->name,
            'mensajeOnboarding' => $whatsapp['mensajeOnboarding'] ?? '',
            'mensajeDashboard' => $whatsapp['mensajeDashboard'] ?? '',
            'linkTexto' => $whatsapp['linkTexto'] ?? '',
            'link' => $link,
        ];
    }
}
