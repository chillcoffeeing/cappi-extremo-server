<?php

namespace App\Actions\Orders;

use App\Exceptions\PaymentActionException;
use App\Models\AdminUser;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * No cancela una orden ya PAGADA: eso requeriria un flujo de reembolso que
 * no esta documentado ni construido. Tampoco una ya CANCELADA.
 */
class CancelOrder
{
    public function handle(Order $order, string $reason, AdminUser $actor): Order
    {
        return DB::transaction(function () use ($order, $reason, $actor): Order {
            $order = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($order->status, ['PAGADA', 'CANCELADA'], true)) {
                throw new PaymentActionException('Solo una orden pendiente de pago puede cancelarse.');
            }

            $order->update([
                'status' => 'CANCELADA',
                'cancellation_reason' => $reason,
                'cancelled_by' => $actor->uuid,
                'cancelled_at' => now(),
            ]);

            return $order->fresh();
        });
    }
}
