<?php

namespace Tests\Feature\Admin;

use App\Actions\Orders\CancelOrder;
use App\Actions\Payments\ApprovePayment;
use App\Actions\Payments\RejectPayment;
use App\Exceptions\PaymentActionException;
use App\Models\AdminUser;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentActionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrderAndPayment(float $total, float $paid, float $paymentAmount): array
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [], 'total' => $total, 'paid' => $paid, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);
        $payment = Payment::create([
            'user_uuid' => $user->uuid, 'order_uuid' => $order->uuid, 'paid_at' => now()->toDateString(),
            'amount' => $paymentAmount, 'currency' => 'USD', 'method_code' => 'met_zelle', 'method_name' => 'Zelle',
            'reference' => 'TX-1', 'status' => 'PENDIENTE_VERIFICACION', 'receipt_name' => 'r.jpg',
            'idempotency_hash' => hash('sha256', uniqid()),
        ]);

        return [$order, $payment];
    }

    public function test_approve_payment_marks_order_paid_when_it_covers_the_full_balance(): void
    {
        [$order, $payment] = $this->makeOrderAndPayment(total: 100, paid: 0, paymentAmount: 100);
        $admin = AdminUser::factory()->create();

        $approved = app(ApprovePayment::class)->handle($payment, $admin);

        $this->assertSame('APROBADO', $approved->status);
        $this->assertSame($admin->uuid, $approved->reviewed_by);
        $this->assertNotNull($approved->reviewed_at);
        $this->assertSame('PAGADA', $order->fresh()->status);
        $this->assertEquals(100, (float) $order->fresh()->paid);
    }

    public function test_approve_payment_keeps_order_pending_on_partial_payment(): void
    {
        [$order, $payment] = $this->makeOrderAndPayment(total: 100, paid: 0, paymentAmount: 40);
        $admin = AdminUser::factory()->create();

        app(ApprovePayment::class)->handle($payment, $admin);

        $this->assertSame('PENDIENTE_PAGO', $order->fresh()->status);
        $this->assertEquals(40, (float) $order->fresh()->paid);
    }

    public function test_approve_payment_rejects_amount_exceeding_balance(): void
    {
        [, $payment] = $this->makeOrderAndPayment(total: 100, paid: 80, paymentAmount: 50);
        $admin = AdminUser::factory()->create();

        $this->expectException(PaymentActionException::class);

        app(ApprovePayment::class)->handle($payment, $admin);
    }

    public function test_approve_payment_rejects_a_payment_that_is_not_pending(): void
    {
        [, $payment] = $this->makeOrderAndPayment(total: 100, paid: 0, paymentAmount: 100);
        $payment->update(['status' => 'APROBADO']);
        $admin = AdminUser::factory()->create();

        $this->expectException(PaymentActionException::class);

        app(ApprovePayment::class)->handle($payment, $admin);
    }

    public function test_reject_payment_requires_reason_and_does_not_touch_order_balance(): void
    {
        [$order, $payment] = $this->makeOrderAndPayment(total: 100, paid: 0, paymentAmount: 100);
        $admin = AdminUser::factory()->create();

        $rejected = app(RejectPayment::class)->handle($payment, 'Comprobante ilegible', $admin);

        $this->assertSame('RECHAZADO', $rejected->status);
        $this->assertSame('Comprobante ilegible', $rejected->rejection_reason);
        $this->assertSame(0.0, (float) $order->fresh()->paid);
        $this->assertSame('PENDIENTE_PAGO', $order->fresh()->status);
    }

    public function test_cancel_order_requires_reason_and_blocks_a_paid_order(): void
    {
        $user = User::factory()->create();
        $pending = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [], 'total' => 50, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);
        $paid = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [], 'total' => 50, 'paid' => 50, 'status' => 'PAGADA', 'is_registration' => false,
        ]);
        $admin = AdminUser::factory()->create();

        $cancelled = app(CancelOrder::class)->handle($pending, 'El cliente desistió', $admin);
        $this->assertSame('CANCELADA', $cancelled->status);
        $this->assertSame($admin->uuid, $cancelled->cancelled_by);

        $this->expectException(PaymentActionException::class);
        app(CancelOrder::class)->handle($paid, 'motivo', $admin);
    }
}
