<?php

namespace Tests\Feature\Admin;

use App\Actions\Orders\CancelOrder;
use App\Actions\Participants\ResolveParticipantCorrection;
use App\Actions\Payments\ApprovePayment;
use App\Actions\Payments\RejectPayment;
use App\Actions\Plans\PublishPlan;
use App\Exceptions\ParticipantActionException;
use App\Exceptions\PaymentActionException;
use App\Exceptions\PlanTransitionException;
use App\Models\AdminUser;
use App\Models\Order;
use App\Models\Participant;
use App\Models\ParticipantCorrectionRequest;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 6 (endurecimiento): un doble clic del admin (doble submit de la misma
 * Action, con el mismo registro, sin recargar la pagina) no debe duplicar
 * efectos ni corromper el estado. Cada Action financiera/de estado ya trae
 * su propio guard de estado (ver el "if status !== ..." de cada una); estas
 * pruebas verifican ese guard explicitamente como escenario de reintento,
 * no solo como regla de negocio aislada.
 */
class DoubleSubmitRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_double_clicking_approve_payment_only_credits_the_order_once(): void
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

        app(ApprovePayment::class)->handle($payment, $admin);
        $this->assertEquals(20, (float) $order->fresh()->paid);

        $this->expectException(PaymentActionException::class);
        app(ApprovePayment::class)->handle($payment->fresh(), $admin);
    }

    public function test_double_clicking_reject_payment_does_not_overwrite_the_reason_twice(): void
    {
        $user = User::factory()->create();
        $payment = Payment::create([
            'user_uuid' => $user->uuid, 'paid_at' => now()->toDateString(),
            'amount' => 20, 'currency' => 'USD', 'method_code' => 'met_zelle', 'method_name' => 'Zelle',
            'reference' => 'TX-2', 'status' => 'PENDIENTE_VERIFICACION', 'receipt_name' => 'r.jpg',
            'idempotency_hash' => hash('sha256', uniqid()),
        ]);
        $admin = AdminUser::factory()->create();

        app(RejectPayment::class)->handle($payment, 'Comprobante ilegible', $admin);

        $this->expectException(PaymentActionException::class);
        app(RejectPayment::class)->handle($payment->fresh(), 'Segundo motivo distinto', $admin);
    }

    public function test_double_clicking_cancel_order_is_rejected_on_the_second_attempt(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [], 'total' => 20, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);
        $admin = AdminUser::factory()->create();

        app(CancelOrder::class)->handle($order, 'El cliente desistió', $admin);

        $this->expectException(PaymentActionException::class);
        app(CancelOrder::class)->handle($order->fresh(), 'reintento', $admin);
    }

    public function test_double_clicking_publish_plan_is_rejected_on_the_second_attempt(): void
    {
        $plan = Plan::create([
            'name' => 'Plan de prueba', 'venue' => 'Sede', 'season' => 'Temporada',
            'status' => 'BORRADOR', 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-05',
            'capacity' => 10, 'price' => 100, 'currency' => 'USD', 'age_min' => 4, 'age_max' => 15,
        ]);

        app(PublishPlan::class)->handle($plan);

        $this->expectException(PlanTransitionException::class);
        app(PublishPlan::class)->handle($plan->fresh());
    }

    public function test_double_clicking_resolve_correction_request_is_rejected_on_the_second_attempt(): void
    {
        $user = User::factory()->create();
        $participant = Participant::factory()->for($user, 'user')->create();
        $admin = AdminUser::factory()->create();
        $request = ParticipantCorrectionRequest::create([
            'participant_uuid' => $participant->uuid, 'section' => 'salud', 'message' => 'Falta info',
            'status' => 'PENDIENTE', 'requested_by' => $admin->uuid,
        ]);

        app(ResolveParticipantCorrection::class)->handle($request, 'Resuelto', $admin);

        $this->expectException(ParticipantActionException::class);
        app(ResolveParticipantCorrection::class)->handle($request->fresh(), 'Resuelto de nuevo', $admin);
    }
}
