<?php

namespace App\Actions\Payments;

use App\Exceptions\PaymentActionException;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Reporte de un pago contra una orden del representante
 * (`POST /ordenes/{id}/enlazar-pago`). Crea un Payment
 * PENDIENTE_VERIFICACION; `orders.paid` solo sube al aprobarlo
 * (ApprovePayment).
 *
 * F-052: extraida del controlador para que las reglas vivan en una Action.
 *  - A-2: una orden CANCELADA no admite pagos.
 *  - A-1: el dedupe ignora los pagos RECHAZADOS (se puede reportar de nuevo
 *    la misma transferencia).
 *  - A-7: el hash usa el monto normalizado a 2 decimales.
 *  - A-8: validacion + creacion dentro de una transaccion con la orden
 *    bloqueada (`lockForUpdate`), y un choque con el indice unico se traduce
 *    al mismo mensaje de duplicado en vez de un 500.
 *
 * Los errores de negocio se lanzan como PaymentActionException (el
 * controlador los responde como `422 { message }`).
 */
class LinkOrderPayment
{
    public const DUPLICATE_MESSAGE = 'Este pago ya fue reportado. Evita duplicados.';

    public const MINIMUM_INSTALLMENT = 20;

    /**
     * @param  array{monto: float|int|string, esCompleto: bool, metodoId: string, metodoNombre: string, referencia: string}  $data
     */
    public function handle(Order $order, array $data, UploadedFile $receipt): Order
    {
        $storedPath = null;

        try {
            DB::transaction(function () use ($order, $data, $receipt, &$storedPath): void {
                $order = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
                $amount = round((float) $data['monto'], 2);

                $this->assertCanReport($order, $amount, (bool) $data['esCompleto']);

                // F-049: la referencia es obligatoria (FormRequest), asi que el
                // hash siempre es orden + referencia + monto normalizado.
                $reference = (string) $data['referencia'];
                $hash = self::reportHash($order, $reference, $amount);

                if (Payment::isDuplicateReport($hash)) {
                    throw new PaymentActionException(self::DUPLICATE_MESSAGE);
                }
                Payment::releaseRejectedHash($hash);

                $storedPath = $receipt->store('comprobantes', 'local');

                Payment::create([
                    'user_uuid' => $order->user_uuid,
                    'paid_at' => now()->toDateString(),
                    'amount' => $amount,
                    'currency' => 'USD',
                    'method_code' => $data['metodoId'],
                    'method_name' => $data['metodoNombre'],
                    'reference' => $reference,
                    // F-046: el item de una orden de inscripción ya se llama
                    // 'Inscripción · <plan>'; se quita ese prefijo para no
                    // duplicarlo (el onboarding usa 'Inscripción - <plan>').
                    'concept' => $order->is_registration
                        ? 'Inscripción - '.Str::after($order->items[0]['nombre'] ?? 'pedido', 'Inscripción · ')
                        : 'Pedido tienda - '.($order->items[0]['nombre'] ?? 'pedido'),
                    'status' => 'PENDIENTE_VERIFICACION',
                    'receipt_path' => $storedPath,
                    'receipt_name' => $receipt->getClientOriginalName(),
                    'order_uuid' => $order->uuid,
                    'idempotency_hash' => $hash,
                ]);
            });
        } catch (\Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }

            if ($exception instanceof UniqueConstraintViolationException) {
                // A-8: doble envio simultaneo; el segundo choca con el indice unico.
                throw new PaymentActionException(self::DUPLICATE_MESSAGE, 0, $exception);
            }

            throw $exception;
        }

        return $order->refresh();
    }

    /**
     * Reglas de negocio de un reporte contra una orden (F-047/F-052), sobre la
     * orden ya bloqueada. F-051: `$ownPendingAmount` es el monto de un pago
     * PENDIENTE_VERIFICACION de la orden que se esta completando (el
     * placeholder del onboarding): no cuenta como "en revision" para el
     * saldo reportable, porque ese mismo pago es el que se reporta.
     *
     * @throws PaymentActionException
     */
    public function assertCanReport(Order $order, float $amount, bool $isFull, float $ownPendingAmount = 0.0): void
    {
        if ($order->status === 'CANCELADA') {
            throw new PaymentActionException('Esta orden está cancelada.');
        }

        if ($order->balance() <= 0) {
            throw new PaymentActionException('Esta orden ya está pagada; no tiene saldo pendiente.');
        }

        // F-047: se valida contra el saldo reportable (saldo - pagos en
        // revisión) para que la suma de reportes no supere el saldo.
        $pending = max(0.0, round($order->pendingVerificationAmount() - $ownPendingAmount, 4));
        $reportable = max(0.0, round($order->balance() - $pending, 4));
        if ($reportable <= 0) {
            throw new PaymentActionException('Esta orden ya tiene pagos en revisión que cubren el saldo pendiente. Espera a que se verifiquen.');
        }

        if ($amount > $reportable) {
            throw new PaymentActionException($pending > 0
                ? 'El monto no puede superar el saldo por reportar ('.Money::format($reportable).'): la orden tiene '.Money::format($pending).' en pagos en revisión.'
                : 'El monto no puede superar el saldo pendiente.');
        }

        if (! $isFull && $amount < self::MINIMUM_INSTALLMENT) {
            throw new PaymentActionException('El abono mínimo es '.Money::format(self::MINIMUM_INSTALLMENT).'.');
        }
    }

    /** Hash de idempotencia de un reporte: orden + referencia + monto normalizado. */
    public static function reportHash(Order $order, string $reference, float|int|string $amount): string
    {
        return hash('sha256', $order->uuid.'|'.$reference.'|'.Money::normalize($amount));
    }
}
