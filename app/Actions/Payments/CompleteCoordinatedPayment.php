<?php

namespace App\Actions\Payments;

use App\Exceptions\PaymentActionException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * F-051: completa el Payment "placeholder" que el onboarding crea cuando el
 * primer pago se coordina por fuera de la app (metodo COORDINADO_REMOTO,
 * PENDIENTE_VERIFICACION, sin comprobante ni referencia; ver
 * CompleteOnboarding y ResolvePendingCoordinatedPayment).
 *
 * En vez de crear un segundo Payment (que duplicaria el reporte y, con la
 * regla de saldo reportable de F-047, quedaria bloqueado por el propio
 * placeholder), se ACTUALIZA el mismo registro con el metodo elegido
 * (cualquier metodo activo), el monto, la referencia y el comprobante. Sigue
 * PENDIENTE_VERIFICACION y entra al flujo normal aprobar/rechazar del admin.
 *
 * Reglas (las de reporte se reutilizan de LinkOrderPayment):
 *  - el pago debe ser del usuario (si no, 404);
 *  - debe ser de una orden de inscripcion, PENDIENTE_VERIFICACION, sin
 *    comprobante ni referencia y con metodo COORDINADO_REMOTO (si no, 422);
 *  - el saldo reportable se calcula SIN contar ese mismo pago;
 *  - orden cancelada, abono minimo, 2 decimales y duplicados como en
 *    enlazar-pago.
 *
 * Idempotencia: todo ocurre en una transaccion con el pago y la orden
 * bloqueados; un segundo envio encuentra el pago ya completado -> 422.
 */
class CompleteCoordinatedPayment
{
    public const ALREADY_COMPLETED_MESSAGE = 'Este pago ya fue reportado. Evita duplicados.';

    public const NOT_COORDINATED_MESSAGE = 'Este pago no es un pago coordinado pendiente de tu inscripción.';

    public function __construct(private readonly LinkOrderPayment $linkOrderPayment) {}

    /**
     * @param  array{monto: float|int|string, esCompleto: bool, metodoId: string, metodoNombre?: string, referencia: string}  $data
     *
     * @throws ModelNotFoundException pago inexistente o ajeno
     * @throws PaymentActionException regla de negocio (422)
     */
    public function handle(User $user, string $paymentUuid, array $data, UploadedFile $receipt): Order
    {
        // Pago inexistente o de otro usuario -> 404 (no se revela su existencia).
        $payment = Payment::where('uuid', $paymentUuid)
            ->where('user_uuid', $user->uuid)
            ->firstOrFail();

        $storedPath = null;
        $orderUuid = null;

        try {
            DB::transaction(function () use ($payment, $data, $receipt, &$storedPath, &$orderUuid): void {
                $payment = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

                // Doble envio: el primero ya dejo comprobante/referencia.
                if ($payment->status === 'PENDIENTE_VERIFICACION'
                    && ($payment->receipt_path !== null || $payment->reference !== null)) {
                    throw new PaymentActionException(self::ALREADY_COMPLETED_MESSAGE);
                }

                if ($payment->status !== 'PENDIENTE_VERIFICACION') {
                    throw new PaymentActionException('Este pago ya fue verificado; no se puede modificar.');
                }

                $currentMethod = PaymentMethod::where('code', $payment->method_code)->first();
                $order = $payment->order_uuid
                    ? Order::where('uuid', $payment->order_uuid)->lockForUpdate()->first()
                    : null;

                if (! $order || ! $order->is_registration || $currentMethod?->type !== 'COORDINADO_REMOTO') {
                    throw new PaymentActionException(self::NOT_COORDINATED_MESSAGE);
                }

                $method = PaymentMethod::where('code', $data['metodoId'])->where('active', true)->first();
                if (! $method) {
                    throw new PaymentActionException('Método de pago no disponible.');
                }

                $amount = round((float) $data['monto'], 2);

                // Saldo reportable SIN contar este mismo pago pendiente.
                $this->linkOrderPayment->assertCanReport(
                    $order,
                    $amount,
                    (bool) $data['esCompleto'],
                    (float) $payment->amount,
                );

                $reference = (string) $data['referencia'];
                $hash = LinkOrderPayment::reportHash($order, $reference, $amount);

                if (Payment::isDuplicateReport($hash)) {
                    throw new PaymentActionException(LinkOrderPayment::DUPLICATE_MESSAGE);
                }
                Payment::releaseRejectedHash($hash);

                $storedPath = $receipt->store('comprobantes', 'local');

                // Mismo registro: el concepto y la orden se conservan. El hash
                // pasa a ser el de un reporte normal (orden + referencia +
                // monto) para que un enlazar-pago posterior con los mismos
                // datos se detecte como duplicado.
                $payment->update([
                    'paid_at' => now()->toDateString(),
                    'amount' => $amount,
                    'method_code' => $method->code,
                    'method_name' => $method->name,
                    'reference' => $reference,
                    'receipt_path' => $storedPath,
                    'receipt_name' => $receipt->getClientOriginalName(),
                    'idempotency_hash' => $hash,
                ]);

                $orderUuid = $order->uuid;
            });
        } catch (\Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }

            if ($exception instanceof UniqueConstraintViolationException) {
                throw new PaymentActionException(LinkOrderPayment::DUPLICATE_MESSAGE, 0, $exception);
            }

            throw $exception;
        }

        return Order::where('uuid', $orderUuid)->firstOrFail();
    }
}
