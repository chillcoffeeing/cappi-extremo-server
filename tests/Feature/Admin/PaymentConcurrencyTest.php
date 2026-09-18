<?php

namespace Tests\Feature\Admin;

use App\Actions\Payments\ApprovePayment;
use App\Exceptions\PaymentActionException;
use App\Models\AdminUser;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 6 (endurecimiento): PHPUnit corre en un solo proceso/conexion, asi
 * que no puede reproducir dos transacciones de verdad compitiendo por el
 * mismo row lock (eso solo se prueba con carga real contra MySQL/Postgres
 * en produccion, donde lockForUpdate() si bloquea entre conexiones - SQLite,
 * que es lo que usan estos tests, bloquea a nivel de archivo completo, no
 * por fila). Lo que SI se puede probar aqui, y es lo que realmente importa
 * para la correccion del codigo: que ApprovePayment relee el estado fresco
 * DENTRO de la transaccion (`Payment::whereKey(...)->lockForUpdate()`) en
 * vez de confiar en el objeto que le paso el llamador. Eso es lo que evita
 * el doble cobro si dos "workers" leyeron el pago ANTES de que cualquiera
 * de los dos terminara de procesarlo.
 */
class PaymentConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_stale_readers_cannot_both_approve_the_same_payment(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [], 'total' => 20, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);
        $payment = Payment::create([
            'user_uuid' => $user->uuid, 'order_uuid' => $order->uuid, 'paid_at' => now()->toDateString(),
            'amount' => 20, 'currency' => 'USD', 'method_code' => 'met_zelle', 'method_name' => 'Zelle',
            'reference' => 'TX-1', 'status' => 'PENDIENTE_VERIFICACION', 'receipt_name' => 'r.jpg',
            'idempotency_hash' => hash('sha256', uniqid()),
        ]);
        $admin = AdminUser::factory()->create();

        // Dos "workers" leen el mismo pago PENDIENTE_VERIFICACION antes de
        // que ninguno de los dos haya procesado nada.
        $readByWorkerA = Payment::find($payment->id);
        $readByWorkerB = Payment::find($payment->id);

        app(ApprovePayment::class)->handle($readByWorkerA, $admin);

        // El worker B todavia tiene en memoria status=PENDIENTE_VERIFICACION,
        // pero la Action relee el estado real dentro de la transaccion y lo
        // rechaza en vez de aprobar dos veces.
        $this->expectException(PaymentActionException::class);
        app(ApprovePayment::class)->handle($readByWorkerB, $admin);
    }

    public function test_order_paid_amount_is_credited_exactly_once_despite_the_race(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [], 'total' => 20, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);
        $payment = Payment::create([
            'user_uuid' => $user->uuid, 'order_uuid' => $order->uuid, 'paid_at' => now()->toDateString(),
            'amount' => 20, 'currency' => 'USD', 'method_code' => 'met_zelle', 'method_name' => 'Zelle',
            'reference' => 'TX-2', 'status' => 'PENDIENTE_VERIFICACION', 'receipt_name' => 'r.jpg',
            'idempotency_hash' => hash('sha256', uniqid()),
        ]);
        $admin = AdminUser::factory()->create();

        $readByWorkerA = Payment::find($payment->id);
        $readByWorkerB = Payment::find($payment->id);

        app(ApprovePayment::class)->handle($readByWorkerA, $admin);

        try {
            app(ApprovePayment::class)->handle($readByWorkerB, $admin);
        } catch (PaymentActionException) {
            // esperado
        }

        $this->assertEquals(20, (float) $order->fresh()->paid);
    }
}
