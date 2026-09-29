<?php

namespace Tests\Feature\Api;

use App\Actions\Orders\CancelOrder;
use App\Actions\Payments\ApprovePayment;
use App\Actions\Payments\RejectPayment;
use App\Exceptions\PaymentActionException;
use App\Models\AdminUser;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LinkOrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_link_a_full_payment_to_their_order_and_download_the_receipt(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [['nombre' => 'Gorra', 'variante' => 'Única', 'qty' => 1, 'precio' => 15]],
            'total' => 15, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);

        $response = $this->post("/api/ordenes/{$order->uuid}/enlazar-pago", [
            'monto' => 15,
            'esCompleto' => true,
            'metodoId' => 'met_zelle',
            'metodoNombre' => 'Zelle',
            'referencia' => 'TX-100',
            'comprobante' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $response->assertOk();
        $paymentUuid = $order->payments()->sole()->uuid;
        $this->get("/api/pagos/{$paymentUuid}/comprobante")->assertOk();
    }

    public function test_registration_order_payment_concept_does_not_duplicate_the_prefix(): void
    {
        // F-046: mismo formato que el pago creado por el onboarding.
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [['nombre' => 'Inscripción · Plan Navidad', 'variante' => '2026-12-14 - 2026-12-18', 'qty' => 1, 'precio' => 300]],
            'total' => 300, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => true,
        ]);

        $this->post("/api/ordenes/{$order->uuid}/enlazar-pago", [
            'monto' => 60,
            'esCompleto' => false,
            'metodoId' => 'met_binance',
            'metodoNombre' => 'Binance',
            'referencia' => 'BN-1',
            'comprobante' => UploadedFile::fake()->image('receipt.png'),
        ])->assertOk();

        $this->assertSame('Inscripción - Plan Navidad', $order->payments()->sole()->concept);
    }

    public function test_amount_cannot_exceed_the_order_balance(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [], 'total' => 15, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);

        $this->post("/api/ordenes/{$order->uuid}/enlazar-pago", [
            'monto' => 50,
            'esCompleto' => true,
            'metodoId' => 'met_zelle',
            'metodoNombre' => 'Zelle',
            'referencia' => 'TX-101',
            'comprobante' => UploadedFile::fake()->image('receipt.jpg'),
        ])
            ->assertStatus(422)
            // F-052 (A-12): el mensaje exacto, no cualquier 422.
            ->assertExactJson(['message' => 'El monto no puede superar el saldo pendiente.']);
    }

    public function test_reportable_balance_discounts_payments_pending_verification(): void
    {
        // F-047 (hallazgo H-1 de F-046): orden de $300 con abonos de $20 y $30
        // en revisión -> reportar "completo" por $300 se rechaza; $250 se acepta.
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [['nombre' => 'Inscripción · Plan Navidad', 'variante' => '2026-12-14 - 2026-12-18', 'qty' => 1, 'precio' => 300]],
            'total' => 300, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => true,
        ]);
        $link = fn (int $monto, bool $completo, string $ref) => $this->post("/api/ordenes/{$order->uuid}/enlazar-pago", [
            'monto' => $monto,
            'esCompleto' => $completo,
            'metodoId' => 'met_binance',
            'metodoNombre' => 'Binance',
            'referencia' => $ref,
            'comprobante' => UploadedFile::fake()->image('receipt.png'),
        ]);

        $link(20, false, 'BN-20')->assertOk();
        $link(30, false, 'BN-30')->assertOk();
        $this->assertSame(250.0, $order->fresh()->reportableBalance());

        $link(300, true, 'BN-300')
            ->assertStatus(422)
            ->assertJson(['message' => 'El monto no puede superar el saldo por reportar ($250.00): la orden tiene $50.00 en pagos en revisión.']);
        $this->assertSame(2, $order->payments()->count());

        $link(250, true, 'BN-250')->assertOk();
        $this->assertSame(0.0, $order->fresh()->reportableBalance());
        $this->assertSame(0.0, (float) $order->fresh()->paid);

        $link(20, false, 'BN-extra')
            ->assertStatus(422)
            ->assertJson(['message' => 'Esta orden ya tiene pagos en revisión que cubren el saldo pendiente. Espera a que se verifiquen.']);
    }

    public function test_amounts_in_reportable_balance_message_always_use_two_decimals(): void
    {
        // F-048: 250.5 -> $250.50 (antes salia "$250.5").
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [], 'total' => 300, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);
        $link = fn (float $monto, bool $completo, string $ref) => $this->post("/api/ordenes/{$order->uuid}/enlazar-pago", [
            'monto' => $monto,
            'esCompleto' => $completo,
            'metodoId' => 'met_zelle',
            'metodoNombre' => 'Zelle',
            'referencia' => $ref,
            'comprobante' => UploadedFile::fake()->image('receipt.png'),
        ]);

        $link(49.5, false, 'Z-49.5')->assertOk();
        $this->assertSame(0.0, (float) $order->fresh()->paid);

        $link(300, true, 'Z-300')
            ->assertStatus(422)
            ->assertJson(['message' => 'El monto no puede superar el saldo por reportar ($250.50): la orden tiene $49.50 en pagos en revisión.']);
    }

    public function test_rejected_payments_free_the_reportable_balance(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [], 'total' => 100, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);
        $order->payments()->create([
            'user_uuid' => $user->uuid, 'paid_at' => now()->toDateString(), 'amount' => 100, 'currency' => 'USD',
            'method_code' => 'met_zelle', 'method_name' => 'Zelle', 'reference' => 'Z-1', 'concept' => 'Pedido tienda - x',
            'status' => 'RECHAZADO', 'receipt_path' => 'comprobantes/x.png', 'receipt_name' => 'x.png', 'idempotency_hash' => 'h-rechazado',
        ]);

        $this->assertSame(100.0, $order->reportableBalance());
    }

    public function test_reference_is_required_and_cannot_be_blank(): void
    {
        // F-049: la referencia vuelve a ser obligatoria (revierte F-035).
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [], 'total' => 100, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);
        $payload = fn (array $extra) => array_merge([
            'monto' => 20,
            'esCompleto' => false,
            'metodoId' => 'met_zelle',
            'metodoNombre' => 'Zelle',
            'comprobante' => UploadedFile::fake()->image('receipt.png'),
        ], $extra);
        $json = ['Accept' => 'application/json'];

        $this->post("/api/ordenes/{$order->uuid}/enlazar-pago", $payload([]), $json)
            ->assertStatus(422)
            ->assertJsonPath('errors.referencia.0', 'Ingresa el número de referencia del pago.');
        $this->post("/api/ordenes/{$order->uuid}/enlazar-pago", $payload(['referencia' => '']), $json)
            ->assertStatus(422)
            ->assertJsonPath('errors.referencia.0', 'Ingresa el número de referencia del pago.');
        $this->post("/api/ordenes/{$order->uuid}/enlazar-pago", $payload(['referencia' => '    ']), $json)
            ->assertStatus(422)
            ->assertJsonPath('errors.referencia.0', 'Ingresa el número de referencia del pago.');
        $this->assertSame(0, $order->payments()->count());

        $this->post("/api/ordenes/{$order->uuid}/enlazar-pago", $payload(['referencia' => '  ZL-9  ']), $json)
            ->assertOk();
        $this->assertSame('ZL-9', $order->payments()->sole()->reference);
    }

    public function test_exact_duplicate_report_is_rejected(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [], 'total' => 100, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);
        $link = fn (string $ref) => $this->post("/api/ordenes/{$order->uuid}/enlazar-pago", [
            'monto' => 20,
            'esCompleto' => false,
            'metodoId' => 'met_zelle',
            'metodoNombre' => 'Zelle',
            'referencia' => $ref,
            'comprobante' => UploadedFile::fake()->image('receipt.png'),
        ], ['Accept' => 'application/json']);

        $link('ZL-DUP')->assertOk();
        $link('ZL-DUP')
            ->assertStatus(422)
            ->assertJson(['message' => 'Este pago ya fue reportado. Evita duplicados.']);
        $link('ZL-OTRA')->assertOk();
        $this->assertSame(2, $order->payments()->count());
    }

    /** @return \Closure(float|int|string, string, bool=): \Illuminate\Testing\TestResponse */
    private function linker(Order $order, string $metodoId = 'met_zelle', string $metodoNombre = 'Zelle'): \Closure
    {
        return fn (float|int|string $monto, string $ref, bool $completo = false) => $this->post("/api/ordenes/{$order->uuid}/enlazar-pago", [
            'monto' => $monto,
            'esCompleto' => $completo,
            'metodoId' => $metodoId,
            'metodoNombre' => $metodoNombre,
            'referencia' => $ref,
            'comprobante' => UploadedFile::fake()->image('receipt.png'),
        ], ['Accept' => 'application/json']);
    }

    private function makeOrder(User $user, float $total, bool $registration = false): Order
    {
        return Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [['nombre' => $registration ? 'Inscripción · Plan Navidad' : 'Gorra', 'variante' => 'x', 'qty' => 1, 'precio' => $total]],
            'total' => $total, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => $registration,
        ]);
    }

    public function test_rejected_payment_can_be_reported_again_with_same_reference_and_amount(): void
    {
        // F-052 (A-1): rechazar libera el hash; pendiente/aprobado lo siguen bloqueando.
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = $this->makeOrder($user, 200);
        $link = $this->linker($order);
        $admin = AdminUser::factory()->create();

        $link(60, 'BN-123')->assertOk();
        $link(60, 'BN-123')->assertStatus(422)->assertExactJson(['message' => 'Este pago ya fue reportado. Evita duplicados.']);

        app(RejectPayment::class)->handle($order->payments()->sole(), 'Comprobante ilegible', $admin);

        $link(60, 'BN-123')->assertOk();
        $this->assertSame(2, $order->payments()->count());
        $this->assertSame(1, $order->payments()->where('status', 'RECHAZADO')->count());

        $second = $order->payments()->where('status', 'PENDIENTE_VERIFICACION')->sole();
        app(ApprovePayment::class)->handle($second, $admin);
        $link(60, 'BN-123')->assertStatus(422)->assertExactJson(['message' => 'Este pago ya fue reportado. Evita duplicados.']);
    }

    public function test_legacy_rejected_payment_keeping_its_hash_does_not_block_the_new_report(): void
    {
        // F-052 (A-1): datos anteriores al arreglo (rechazados sin hash liberado).
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = $this->makeOrder($user, 200);
        $order->payments()->create([
            'user_uuid' => $user->uuid, 'paid_at' => now()->toDateString(), 'amount' => 60, 'currency' => 'USD',
            'method_code' => 'met_zelle', 'method_name' => 'Zelle', 'reference' => 'BN-123', 'concept' => 'Pedido tienda - Gorra',
            'status' => 'RECHAZADO', 'receipt_path' => 'comprobantes/x.png', 'receipt_name' => 'x.png',
            'idempotency_hash' => hash('sha256', $order->uuid.'|BN-123|60.00'),
        ]);

        $this->linker($order)(60, 'BN-123')->assertOk();
        $this->assertSame(2, $order->payments()->count());
    }

    public function test_amount_text_is_normalized_in_the_duplicate_hash_and_limited_to_two_decimals(): void
    {
        // F-052 (A-7)
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = $this->makeOrder($user, 200);
        $link = $this->linker($order);

        $link('20.123', 'ZL-1')->assertStatus(422)
            ->assertJsonPath('errors.monto.0', 'El monto admite como máximo 2 decimales.');
        $link('20', 'ZL-1')->assertOk();
        $link('20.00', 'ZL-1')->assertStatus(422)->assertExactJson(['message' => 'Este pago ya fue reportado. Evita duplicados.']);
        $link('20.0', 'ZL-1')->assertStatus(422);
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_concurrent_duplicate_hitting_the_unique_index_returns_422_not_500(): void
    {
        // F-052 (A-8): simula que otro request identico inserto la fila justo
        // despues del chequeo de duplicado.
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = $this->makeOrder($user, 200);
        $raced = false;
        Payment::creating(function (Payment $payment) use (&$raced): void {
            if ($raced) {
                return;
            }
            $raced = true;
            Payment::withoutEvents(fn () => Payment::create([
                ...$payment->getAttributes(),
                'receipt_path' => 'comprobantes/otro.png',
            ]));
        });

        $this->linker($order)(20, 'ZL-RACE')
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Este pago ya fue reportado. Evita duplicados.']);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_cancelled_order_cannot_receive_payments_nor_be_revived_by_approval(): void
    {
        // F-052 (A-2)
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = $this->makeOrder($user, 80);
        $admin = AdminUser::factory()->create();
        $this->linker($order)(30, 'ZL-A')->assertOk();

        app(CancelOrder::class)->handle($order, 'Pedido duplicado', $admin);

        $this->linker($order)(20, 'ZL-B')
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Esta orden está cancelada.']);
        $this->assertSame(1, $order->payments()->count());

        try {
            app(ApprovePayment::class)->handle($order->payments()->sole(), $admin);
            $this->fail('Se aprobó un pago de una orden cancelada.');
        } catch (PaymentActionException $exception) {
            $this->assertSame('No se puede aprobar un pago de una orden cancelada. Recházalo.', $exception->getMessage());
        }
        $this->assertSame('CANCELADA', $order->fresh()->status);
        $this->assertSame(0.0, (float) $order->fresh()->paid);
        $this->assertSame('PENDIENTE_VERIFICACION', $order->payments()->sole()->status);
    }

    public function test_link_payment_with_a_coordinated_remote_method_follows_the_same_flow(): void
    {
        // F-052 (A-12/B-1): mismo flujo que un metodo DIRECTO, y un reporte
        // con comprobante no activa el banner de primer pago coordinado.
        Storage::fake('local');
        PaymentMethod::create([
            'code' => 'met_coord', 'type' => 'COORDINADO_REMOTO', 'name' => 'Coordinado',
            'description' => 'x', 'data' => ['whatsapp' => ['link' => 'https://wa.me/1']], 'active' => true,
        ]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = $this->makeOrder($user, 300, registration: true);

        $this->linker($order, 'met_coord', 'Coordinado')(50, 'CO-1')->assertOk();

        $payment = $order->payments()->sole();
        $this->assertSame('PENDIENTE_VERIFICACION', $payment->status);
        $this->assertNotNull($payment->receipt_path);
        $this->getJson('/api/pagos/balance')->assertOk()->assertJsonPath('primerPagoCoordinado', null);
    }

    public function test_business_messages_use_the_shared_money_format(): void
    {
        // F-052 (A-4): $1,250.50 (coma de miles, 2 decimales).
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = $this->makeOrder($user, 2000);
        $link = $this->linker($order);

        $link('749.50', 'ZL-1')->assertOk();
        $link(2000, 'ZL-2', true)
            ->assertStatus(422)
            ->assertExactJson(['message' => 'El monto no puede superar el saldo por reportar ($1,250.50): la orden tiene $749.50 en pagos en revisión.']);
        $link(10, 'ZL-3')->assertStatus(422)->assertExactJson(['message' => 'El abono mínimo es $20.00.']);
    }
}
