<?php

namespace App\Actions\Payments;

use App\Exceptions\PaymentActionException;
use App\Models\AdminUser;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Sigue los 10 pasos de api/docs/backoffice/05-operacion-financiera.md, salvo
 * dos que no tienen con que implementarse hoy (ver notas):
 *
 * 7. "Actualizar inscripcion relacionada" - NO implementado. `enrollments`
 *    no tiene relacion explicita con `orders` (gap ya documentado en
 *    02-datos-y-migraciones.md, anterior a esta Action). Adivinar que
 *    enrollment corresponde por heuristica (mismo usuario) podria marcar
 *    como pagada una inscripcion equivocada; es peor que no tocarla.
 * 10. "Notificar al representante" - NO implementado. No existe canal de
 *    notificaciones configurado en la app (sin `notifications` table, sin
 *    mailer transaccional). Construirlo es un proyecto aparte.
 */
class ApprovePayment
{
    public function handle(Payment $payment, AdminUser $actor): Payment
    {
        return DB::transaction(function () use ($payment, $actor): Payment {
            $payment = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'PENDIENTE_VERIFICACION') {
                throw new PaymentActionException('Este pago ya fue procesado.');
            }

            $order = null;
            if ($payment->order_uuid) {
                $order = $payment->order()->lockForUpdate()->first();

                $balance = (float) $order->total - (float) $order->paid;
                if ((float) $payment->amount > $balance) {
                    throw new PaymentActionException('El monto del pago supera el saldo pendiente de la orden.');
                }
            }

            $payment->update([
                'status' => 'APROBADO',
                'reviewed_by' => $actor->uuid,
                'reviewed_at' => now(),
            ]);

            if ($order) {
                $newPaid = (float) $order->paid + (float) $payment->amount;
                $order->update([
                    'paid' => $newPaid,
                    'status' => $newPaid >= (float) $order->total ? 'PAGADA' : 'PENDIENTE_PAGO',
                ]);
            }

            return $payment->fresh();
        });
    }
}
