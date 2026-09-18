<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Models\AdminUser;
use App\Models\Plan;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanLifecycleActionsUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_action_requires_reason_and_updates_the_plan(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');
        $this->actingAs($admin, 'admin');

        $plan = Plan::create([
            'name' => 'Plan de prueba', 'venue' => 'Sede', 'season' => 'Temporada',
            'status' => 'PUBLICADO', 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-05',
            'capacity' => 10, 'price' => 100, 'currency' => 'USD', 'age_min' => 4, 'age_max' => 15,
        ]);

        Livewire::test(EditPlan::class, ['record' => $plan->getKey()])
            ->callAction('cancel', data: ['reason' => 'Baja demanda'])
            ->assertHasNoActionErrors();

        $plan->refresh();
        $this->assertSame('CANCELADO', $plan->status);
        $this->assertSame('Baja demanda', $plan->status_reason);
    }

    public function test_lectura_role_cannot_open_the_edit_page_at_all(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('LECTURA');
        $this->actingAs($admin, 'admin');

        $plan = Plan::create([
            'name' => 'Plan de prueba', 'venue' => 'Sede', 'season' => 'Temporada',
            'status' => 'PUBLICADO', 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-05',
            'capacity' => 10, 'price' => 100, 'currency' => 'USD', 'age_min' => 4, 'age_max' => 15,
        ]);

        // LECTURA tiene plans.view (lista/detalle) pero no plans.manage, que
        // gobierna PlanPolicy::update() -> Filament bloquea /edit entero.
        $this->get("/admin/plans/{$plan->getKey()}/edit")->assertForbidden();
        $this->get('/admin/plans')->assertOk()->assertSee('Plan de prueba');
    }
}
