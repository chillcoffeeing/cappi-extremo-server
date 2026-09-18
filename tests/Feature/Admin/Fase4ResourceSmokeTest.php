<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase4ResourceSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): AdminUser
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        return $admin;
    }

    public function test_orders_list_shows_orders_table_then_payments_widget(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create();
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [['nombre' => 'Gorra', 'variante' => 'Única', 'qty' => 1, 'precio' => 15]],
            'total' => 15, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);
        Payment::create([
            'user_uuid' => $user->uuid, 'paid_at' => now()->toDateString(), 'amount' => 20,
            'currency' => 'USD', 'method_code' => 'met_zelle', 'method_name' => 'Zelle',
            'reference' => 'TX-1', 'status' => 'PENDIENTE_VERIFICACION', 'receipt_name' => 'r.jpg',
            'idempotency_hash' => hash('sha256', uniqid()),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('TX-1')
            ->assertSee('Aprobar')
            ->assertSee('Rechazar');

        // La tabla de ordenes va primero; la de pagos, debajo (widget de pie).
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'TX-1'), strpos($html, $order->uuid));
    }

    public function test_order_details_page_is_read_only_without_export(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create();
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [['nombre' => 'Gorra', 'variante' => 'Única', 'qty' => 1, 'precio' => 15]],
            'total' => 15, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);

        $this->actingAs($admin, 'admin')->get('/admin/orders')->assertOk();
        $this->actingAs($admin, 'admin')
            ->get("/admin/orders/{$order->getKey()}/details")
            ->assertOk()
            ->assertSee('Cancelar orden')
            ->assertSee('Gorra')
            ->assertDontSee('Exportar')
            ->assertDontSee('Guardar cambios');

        // /edit ya no existe para ordenes.
        $this->actingAs($admin, 'admin')->get("/admin/orders/{$order->getKey()}/edit")->assertNotFound();
    }

    public function test_payment_methods_crud_pages_render(): void
    {
        $admin = $this->superAdmin();
        $this->seed(PaymentMethodSeeder::class);
        $method = PaymentMethod::first();

        $this->actingAs($admin, 'admin')->get('/admin/payment-methods')->assertOk()->assertSee('Zelle');
        $this->actingAs($admin, 'admin')->get('/admin/payment-methods/create')->assertOk();
        $this->actingAs($admin, 'admin')->get("/admin/payment-methods/{$method->code}/edit")->assertOk();
    }

    public function test_products_crud_pages_render(): void
    {
        $admin = $this->superAdmin();
        Product::create([
            'name' => 'Gorra Xtremo', 'category' => 'Ropa', 'price' => 15,
            'variants' => ['Talla única'], 'images' => [], 'description' => 'Gorra', 'in_stock' => true,
        ]);

        $this->actingAs($admin, 'admin')->get('/admin/products')->assertOk()->assertSee('Gorra Xtremo');
        $this->actingAs($admin, 'admin')->get('/admin/products/create')->assertOk();
    }
}
