<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_with_kpis_for_super_admin(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        $user = User::factory()->create();
        Participant::factory()->for($user, 'user')->create(['data_completed' => false]);
        Payment::create([
            'user_uuid' => $user->uuid, 'paid_at' => now()->subDays(10)->toDateString(),
            'amount' => 20, 'currency' => 'USD', 'method_code' => 'met_zelle', 'method_name' => 'Zelle',
            'reference' => 'TX-1', 'status' => 'PENDIENTE_VERIFICACION', 'receipt_name' => 'r.jpg',
            'idempotency_hash' => hash('sha256', uniqid()),
        ]);
        Plan::create([
            'name' => 'Plan de prueba', 'venue' => 'Sede', 'season' => 'Temporada',
            'status' => 'PUBLICADO', 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-05',
            'capacity' => 10, 'price' => 100, 'currency' => 'USD', 'age_min' => 4, 'age_max' => 15,
        ]);
        Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [], 'total' => 15, 'paid' => 15, 'status' => 'PAGADA', 'is_registration' => false,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('Pagos pendientes')
            ->assertSee('Pedidos de Tienda')
            ->assertSee('Representantes')
            ->assertSee('Fichas de participantes incompletas')
            ->assertSee('Cupos disponibles')
            ->assertSee('Actividad reciente')
            ->assertDontSee('0-2 días')
            ->assertDontSee('+7 días');

        // Actividad reciente (widget de menor prioridad) debe renderizar
        // despues de los KPIs, no antes.
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'Actividad reciente'), strpos($html, 'Pagos pendientes'));
    }

    public function test_soporte_role_does_not_see_financial_kpis(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SOPORTE');

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Pagos pendientes')
            ->assertSee('Representantes');
    }
}
