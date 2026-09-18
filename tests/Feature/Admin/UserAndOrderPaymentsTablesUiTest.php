<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Orders\Widgets\PaymentsWidget;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\EnrollmentsRelationManager;
use App\Filament\Resources\Users\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Users\RelationManagers\PaymentsRelationManager as UserPaymentsRelationManager;
use App\Filament\Resources\Orders\RelationManagers\PaymentsRelationManager as OrderPaymentsRelationManager;
use App\Filament\Widgets\ActividadReciente;
use App\Models\AdminUser;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F-018: filas clicables en Inscripciones/Ordenes del expediente del
 * representante, tabla de Pagos completa (columnas + aprobar/rechazar) en
 * los 3 lugares donde aparece, y widgets de tabla a ancho completo.
 */
class UserAndOrderPaymentsTablesUiTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): AdminUser
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        return $admin;
    }

    public function test_enrollments_relation_manager_rows_link_to_enrollment_details(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin, 'admin');

        $user = User::factory()->create();
        $participant = Participant::factory()->for($user, 'user')->create();
        $enrollment = Enrollment::create([
            'participant_uuid' => $participant->uuid,
            'plan_name' => 'Plan Xtremo', 'session_name' => 'Vespertina',
            'status' => 'PENDIENTE_PAGO', 'plan_type' => 'INDIVIDUAL',
            'total_amount' => 100, 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-05',
        ]);

        Livewire::test(EnrollmentsRelationManager::class, [
            'ownerRecord' => $user,
            'pageClass' => ViewUser::class,
        ])->assertTableActionHasUrl(
            'view',
            route('filament.admin.resources.enrollments.view', $enrollment),
            record: $enrollment,
        );
    }

    public function test_orders_relation_manager_rows_link_to_order_details(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin, 'admin');

        $user = User::factory()->create();
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [], 'total' => 20, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);

        Livewire::test(OrdersRelationManager::class, [
            'ownerRecord' => $user,
            'pageClass' => ViewUser::class,
        ])->assertTableActionHasUrl(
            'view',
            route('filament.admin.resources.orders.view', $order),
            record: $order,
        );
    }

    public function test_user_payments_relation_manager_shows_full_columns_and_approve_action(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin, 'admin');

        $user = User::factory()->create(['name' => 'Ana Torres']);
        $payment = Payment::create([
            'user_uuid' => $user->uuid, 'paid_at' => now()->toDateString(),
            'amount' => 50, 'currency' => 'USD', 'method_code' => 'met_zelle', 'method_name' => 'Zelle',
            'reference' => 'TX-USR-1', 'status' => 'PENDIENTE_VERIFICACION', 'receipt_name' => 'r.jpg',
            'idempotency_hash' => hash('sha256', uniqid()),
        ]);

        $component = Livewire::test(UserPaymentsRelationManager::class, [
            'ownerRecord' => $user,
            'pageClass' => ViewUser::class,
        ]);

        $component->assertTableColumnExists('reference')
            ->assertTableColumnExists('amount')
            ->assertTableColumnExists('method_name')
            ->assertTableColumnExists('user.name')
            ->assertTableColumnExists('order.uuid')
            ->assertTableColumnExists('status')
            ->assertTableColumnExists('paid_at')
            ->assertTableColumnExists('reviewedBy.name')
            ->callTableAction('approve', $payment)
            ->assertHasNoTableActionErrors();

        $this->assertSame('APROBADO', $payment->fresh()->status);
    }

    public function test_order_payments_relation_manager_shows_full_columns_and_reject_action(): void
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
            'reference' => 'TX-ORD-1', 'status' => 'PENDIENTE_VERIFICACION', 'receipt_name' => 'r.jpg',
            'idempotency_hash' => hash('sha256', uniqid()),
        ]);

        $component = Livewire::test(OrderPaymentsRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => \App\Filament\Resources\Orders\Pages\ViewOrder::class,
        ]);

        $component->assertTableColumnExists('reference')
            ->assertTableColumnExists('amount')
            ->assertTableColumnExists('method_name')
            ->assertTableColumnExists('user.name')
            ->assertTableColumnExists('order.uuid')
            ->assertTableColumnExists('status')
            ->assertTableColumnExists('paid_at')
            ->assertTableColumnExists('reviewedBy.name')
            ->callTableAction('reject', $payment, data: ['reason' => 'Comprobante ilegible'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('RECHAZADO', $payment->fresh()->status);
    }

    public function test_payments_widget_and_actividad_reciente_span_full_width(): void
    {
        $this->assertSame('full', (new PaymentsWidget())->getColumnSpan());
        $this->assertSame('full', (new ActividadReciente())->getColumnSpan());
    }
}
