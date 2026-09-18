<?php

namespace App\Actions\Payments;

use App\Exceptions\PaymentActionException;
use App\Models\AdminUser;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * "No tocar orders.paid. Mantener la orden pendiente. Permitir un nuevo
 * reporte." (05-operacion-financiera.md) - por eso RejectPayment nunca
 * escribe en `order`, solo en el propio pago.
 */
class RejectPayment
{
    public function handle(Payment $payment, string $reason, AdminUser $actor): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $actor): Payment {
            $payment = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'PENDIENTE_VERIFICACION') {
                throw new PaymentActionException('Este pago ya fue procesado.');
            }

            $payment->update([
                'status' => 'RECHAZADO',
                'rejection_reason' => $reason,
                'reviewed_by' => $actor->uuid,
                'reviewed_at' => now(),
            ]);

            return $payment->fresh();
        });
    }
}
