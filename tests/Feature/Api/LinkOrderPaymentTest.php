<?php

namespace Tests\Feature\Api;

use App\Models\Order;
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
        ])->assertStatus(422);
    }
}
