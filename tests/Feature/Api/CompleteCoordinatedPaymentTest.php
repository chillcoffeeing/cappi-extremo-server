<?php

namespace Tests\Feature\Api;

use App\Actions\Payments\ApprovePayment;
use App\Actions\Payments\RejectPayment;
use App\Models\AdminUser;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * F-051: "Reportar acá" del banner primerPagoCoordinado COMPLETA el Payment
 * placeholder del onboarding (mismo registro) en vez de crear otro.
 */
class CompleteCoordinatedPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Order $order;

    private Payment $placeholder;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        PaymentMethod::create([
            'code' => 'met_coord', 'type' => 'COORDINADO_REMOTO', 'name' => 'Pago coordinado',
            'description' => 'Coordina por WhatsApp.',
            'data' => ['whatsapp' => ['mensajeOnboarding' => '', 'mensajeDashboard' => 'Coordina', 'linkTexto' => '', 'link' => 'https://wa.me/1']],
            'active' => true,
        ]);
        PaymentMethod::create([
            'code' => 'met_zelle', 'type' => 'DIRECTO', 'name' => 'Zelle', 'description' => 'Zelle',
            'data' => ['instrucciones' => '', 'detalle' => []], 'active' => true,
        ]);
        PaymentMethod::create([
            'code' => 'met_old', 'type' => 'DIRECTO', 'name' => 'Inactivo', 'description' => 'x',
            'data' => [], 'active' => false,
        ]);

        [$this->user, $this->order, $this->placeholder] = $this->makeFamily(amount: 60, total: 300);
        Sanctum::actingAs($this->user);
    }

    /** @return array{0: User, 1: Order, 2: Payment} */
    private function makeFamily(float $amount, float $total): array
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [['nombre' => 'Inscripción · Plan', 'variante' => '-', 'qty' => 1, 'precio' => $total]],
            'total' => $total, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => true,
        ]);
        $payment = Payment::create([
            'user_uuid' => $user->uuid, 'order_uuid' => $order->uuid,
            'paid_at' => now()->subDays(3)->toDateString(), 'amount' => $amount, 'currency' => 'USD',
            'method_code' => 'met_coord', 'method_name' => 'Pago coordinado', 'reference' => null,
            'concept' => 'Inscripción - Plan', 'status' => 'PENDIENTE_VERIFICACION',
            'receipt_name' => 'Pago coordinado por WhatsApp',
            'idempotency_hash' => hash('sha256', 'onboarding|'.$user->uuid),
        ]);

        return [$user, $order, $payment];
    }

    /** @param  array<string, mixed>  $overrides */
    private function complete(?string $paymentUuid = null, array $overrides = []): TestResponse
    {
        return $this->post('/api/pagos/'.($paymentUuid ?? $this->placeholder->uuid).'/completar-coordinado', [
            'monto' => 60,
            'esCompleto' => false,
            'metodoId' => 'met_zelle',
            'metodoNombre' => 'Zelle',
            'referencia' => 'ZL-777',
            'comprobante' => UploadedFile::fake()->image('receipt.jpg'),
            ...$overrides,
        ], ['Accept' => 'application/json']);
    }

    public function test_balance_exposes_order_payment_and_amount_of_the_placeholder(): void
    {
        $this->getJson('/api/pagos/balance')
            ->assertOk()
            ->assertJsonPath('primerPagoCoordinado.ordenId', $this->order->uuid)
            ->assertJsonPath('primerPagoCoordinado.pagoId', $this->placeholder->uuid)
            ->assertJsonPath('primerPagoCoordinado.monto', 60);
    }

    public function test_completing_updates_the_same_payment_and_clears_the_banner(): void
    {
        $this->complete(overrides: ['monto' => 80.5])
            ->assertOk()
            ->assertJsonPath('data.id', $this->order->uuid);

        $payment = $this->order->payments()->sole();
        $this->assertSame($this->placeholder->id, $payment->id);
        $this->assertSame('PENDIENTE_VERIFICACION', $payment->status);
        $this->assertSame('ZL-777', $payment->reference);
        $this->assertSame('met_zelle', $payment->method_code);
        $this->assertSame('Zelle', $payment->method_name);
        $this->assertEquals(80.5, (float) $payment->amount);
        $this->assertSame('receipt.jpg', $payment->receipt_name);
        $this->assertNotNull($payment->receipt_path);
        Storage::disk('local')->assertExists($payment->receipt_path);
        $this->assertSame('Inscripción - Plan', $payment->concept);
        $this->assertSame(now()->toDateString(), $payment->paid_at->toDateString());

        $this->getJson('/api/pagos/balance')->assertOk()->assertJsonPath('primerPagoCoordinado', null);
        $this->getJson('/api/pagos')->assertOk()->assertJsonCount(1);
        $this->get("/api/pagos/{$payment->uuid}/comprobante")->assertOk();
    }

    public function test_second_submission_does_not_duplicate_and_returns_422(): void
    {
        $this->complete()->assertOk();

        $this->complete(overrides: ['referencia' => 'OTRA'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este pago ya fue reportado. Evita duplicados.');

        $this->assertSame(1, Payment::count());
        $this->assertSame('ZL-777', $this->placeholder->refresh()->reference);
        $this->assertCount(1, Storage::disk('local')->allFiles('comprobantes'));
    }

    public function test_foreign_or_unknown_payment_returns_404(): void
    {
        [, , $foreign] = $this->makeFamily(amount: 60, total: 300);

        $this->complete($foreign->uuid)->assertNotFound();
        $this->complete('00000000-0000-0000-0000-000000000000')->assertNotFound();

        $this->assertNull($foreign->refresh()->receipt_path);
    }

    public function test_payment_already_completed_or_verified_returns_422(): void
    {
        // Reportado desde el portal (ya trae comprobante y referencia).
        $this->placeholder->update(['reference' => 'X-1', 'receipt_path' => 'comprobantes/x.jpg']);
        $this->complete()->assertStatus(422);

        // Aprobado por el admin.
        [$user, , $approved] = $this->makeFamily(amount: 60, total: 300);
        Sanctum::actingAs($user);
        app(ApprovePayment::class)->handle($approved, AdminUser::factory()->create());
        $this->complete($approved->uuid)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este pago ya fue verificado; no se puede modificar.');
    }

    public function test_rejected_placeholder_cannot_be_completed(): void
    {
        app(RejectPayment::class)->handle($this->placeholder, 'No llegó', AdminUser::factory()->create());

        $this->complete()->assertStatus(422);
    }

    public function test_non_coordinated_or_non_registration_payment_returns_422(): void
    {
        $this->placeholder->update(['method_code' => 'met_zelle']);
        $this->complete()
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este pago no es un pago coordinado pendiente de tu inscripción.');

        $this->placeholder->update(['method_code' => 'met_coord']);
        $this->order->update(['is_registration' => false]);
        $this->complete()->assertStatus(422);
    }

    public function test_amount_is_limited_to_the_reportable_balance_without_counting_the_placeholder(): void
    {
        // Otro pago en revisión de 100: reportable = 300 - 100 = 200 (el
        // placeholder de 60 no cuenta).
        Payment::create([
            'user_uuid' => $this->user->uuid, 'order_uuid' => $this->order->uuid,
            'paid_at' => now()->toDateString(), 'amount' => 100, 'currency' => 'USD',
            'method_code' => 'met_zelle', 'method_name' => 'Zelle', 'reference' => 'A',
            'concept' => 'x', 'status' => 'PENDIENTE_VERIFICACION', 'receipt_path' => 'comprobantes/a.jpg',
            'receipt_name' => 'a.jpg', 'idempotency_hash' => hash('sha256', 'other'),
        ]);

        $this->complete(overrides: ['monto' => 200.01])
            ->assertStatus(422)
            ->assertExactJson(['message' => 'El monto no puede superar el saldo por reportar ($200.00): la orden tiene $100.00 en pagos en revisión.']);
        $this->assertNull($this->placeholder->refresh()->receipt_path);

        $this->complete(overrides: ['monto' => 200, 'esCompleto' => true])->assertOk();
    }

    public function test_full_modality_placeholder_can_report_up_to_the_full_balance(): void
    {
        [$user, $order, $payment] = $this->makeFamily(amount: 300, total: 300);
        Sanctum::actingAs($user);

        $this->complete($payment->uuid, ['monto' => 300.01, 'esCompleto' => true])->assertStatus(422);
        $this->complete($payment->uuid, ['monto' => 300, 'esCompleto' => true])->assertOk();

        $this->assertSame(1, $order->payments()->count());
        $this->assertEquals(300, (float) $payment->refresh()->amount);
    }

    public function test_validation_rules_mirror_link_payment(): void
    {
        $this->complete(overrides: ['referencia' => '   '])->assertStatus(422)->assertJsonValidationErrors('referencia');
        $this->complete(overrides: ['comprobante' => null])->assertStatus(422)->assertJsonValidationErrors('comprobante');
        $this->complete(overrides: ['monto' => 20.123])->assertStatus(422)->assertJsonValidationErrors('monto');
        $this->complete(overrides: ['monto' => 10])
            ->assertStatus(422)
            ->assertJsonPath('message', 'El abono mínimo es $20.00.');
        $this->complete(overrides: ['metodoId' => 'met_old'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Método de pago no disponible.');

        $this->assertNull($this->placeholder->refresh()->receipt_path);
        $this->assertSame([], Storage::disk('local')->allFiles('comprobantes'));
    }

    public function test_cancelled_order_cannot_be_completed(): void
    {
        $this->order->update(['status' => 'CANCELADA']);

        $this->complete()->assertStatus(422)->assertJsonPath('message', 'Esta orden está cancelada.');
    }

    public function test_completed_payment_is_detected_as_duplicate_by_link_payment_and_can_be_approved(): void
    {
        $this->complete()->assertOk();

        $this->post("/api/ordenes/{$this->order->uuid}/enlazar-pago", [
            'monto' => 60, 'esCompleto' => false, 'metodoId' => 'met_zelle', 'metodoNombre' => 'Zelle',
            'referencia' => 'ZL-777', 'comprobante' => UploadedFile::fake()->image('r.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este pago ya fue reportado. Evita duplicados.');

        app(ApprovePayment::class)->handle($this->placeholder->refresh(), AdminUser::factory()->create());
        $this->assertEquals(60, (float) $this->order->refresh()->paid);
    }
}
