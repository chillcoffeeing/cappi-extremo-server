<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Widgets\PaymentsWidget;
use App\Models\AdminUser;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentAndOrderActionsUiTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): AdminUser
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        return $admin;
    }

    public function test_approving_a_payment_through_the_table_action_updates_order_balance(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin, 'admin');

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

        Livewire::test(PaymentsWidget::class)
            ->callTableAction('approve', $payment)
            ->assertHasNoTableActionErrors();

        $this->assertSame('APROBADO', $payment->fresh()->status);
        $this->assertSame('PAGADA', $order->fresh()->status);
    }

    public function test_rejecting_a_payment_requires_a_reason(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin, 'admin');

        $user = User::factory()->create();
        $payment = Payment::create([
            'user_uuid' => $user->uuid, 'paid_at' => now()->toDateString(),
            'amount' => 20, 'currency' => 'USD', 'method_code' => 'met_zelle', 'method_name' => 'Zelle',
            'reference' => 'TX-2', 'status' => 'PENDIENTE_VERIFICACION', 'receipt_name' => 'r.jpg',
            'idempotency_hash' => hash('sha256', uniqid()),
        ]);

        Livewire::test(PaymentsWidget::class)
            ->callTableAction('reject', $payment, data: ['reason' => 'Comprobante ilegible'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('RECHAZADO', $payment->fresh()->status);
        $this->assertSame('Comprobante ilegible', $payment->fresh()->rejection_reason);
    }

    public function test_cancelling_an_order_through_the_edit_page_action(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin, 'admin');

        $user = User::factory()->create();
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [], 'total' => 20, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction('cancel', data: ['reason' => 'El cliente desistió'])
            ->assertHasNoActionErrors();

        $this->assertSame('CANCELADA', $order->fresh()->status);
    }
}
