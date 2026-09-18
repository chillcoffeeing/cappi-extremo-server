<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Plan;
use App\Models\PlanDay;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanResourceSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): AdminUser
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        return $admin;
    }

    public function test_plans_list_page_renders(): void
    {
        $admin = $this->superAdmin();
        Plan::create([
            'name' => 'Plan de prueba', 'venue' => 'Sede', 'season' => 'Temporada',
            'status' => 'PUBLICADO', 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-05',
            'capacity' => 10, 'price' => 100, 'currency' => 'USD', 'age_min' => 4, 'age_max' => 15,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/plans')
            ->assertOk()
            ->assertSee('Plan de prueba');
    }

    public function test_plan_create_page_renders(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admin')
            ->get('/admin/plans/create')
            ->assertOk();
    }

    public function test_plan_edit_page_renders_with_days_and_announcements(): void
    {
        $admin = $this->superAdmin();
        $plan = Plan::create([
            'name' => 'Plan de prueba', 'venue' => 'Sede', 'season' => 'Temporada',
            'status' => 'PUBLICADO', 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-05',
            'capacity' => 10, 'price' => 100, 'currency' => 'USD', 'age_min' => 4, 'age_max' => 15,
        ]);
        PlanDay::create(['plan_uuid' => $plan->uuid, 'day_number' => 1, 'date' => '2026-12-01', 'title' => 'Día 1']);

        $this->actingAs($admin, 'admin')
            ->get("/admin/plans/{$plan->getKey()}/edit")
            ->assertOk()
            ->assertSee('Pausar')
            ->assertSee('Cancelar')
            ->assertDontSee('Publicar');
    }

    public function test_plan_edit_page_shows_sibling_discount_fields_in_precio_y_cupos_tab(): void
    {
        $admin = $this->superAdmin();
        $plan = Plan::create([
            'name' => 'Plan de prueba', 'venue' => 'Sede', 'season' => 'Temporada',
            'status' => 'PUBLICADO', 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-05',
            'capacity' => 10, 'price' => 100, 'currency' => 'USD', 'age_min' => 4, 'age_max' => 15,
        ]);

        $this->actingAs($admin, 'admin')
            ->get("/admin/plans/{$plan->getKey()}/edit")
            ->assertOk()
            ->assertSee('Precio y cupos')
            ->assertSee('Descuento por hermanos activo')
            ->assertSee('Mínimo de participantes de la familia')
            ->assertSee('Descuento por participante');
    }

    public function test_saving_plan_form_persists_sibling_discount_fields(): void
    {
        $admin = $this->superAdmin();
        $plan = Plan::create([
            'name' => 'Plan de prueba', 'venue' => 'Sede', 'season' => 'Temporada',
            'status' => 'PUBLICADO', 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-05',
            'capacity' => 10, 'price' => 100, 'currency' => 'USD', 'age_min' => 4, 'age_max' => 15,
            'available_slots' => 10,
        ]);

        \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Filament\Resources\Plans\Pages\EditPlan::class, ['record' => $plan->getKey()])
            ->fillForm([
                'sibling_discount_enabled' => false,
                'sibling_discount_min_participants' => 4,
                'sibling_discount_amount' => 35,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $plan->refresh();
        $this->assertFalse($plan->sibling_discount_enabled);
        $this->assertSame(4, $plan->sibling_discount_min_participants);
        $this->assertSame('35.0000', (string) $plan->sibling_discount_amount);
    }

    public function test_draft_plan_edit_page_shows_publish_but_not_pause(): void
    {
        $admin = $this->superAdmin();
        $plan = Plan::create([
            'name' => 'Plan borrador', 'venue' => 'Sede', 'season' => 'Temporada',
            'status' => 'BORRADOR', 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-05',
            'capacity' => 10, 'price' => 100, 'currency' => 'USD', 'age_min' => 4, 'age_max' => 15,
        ]);

        $this->actingAs($admin, 'admin')
            ->get("/admin/plans/{$plan->getKey()}/edit")
            ->assertOk()
            ->assertSee('Publicar')
            ->assertDontSee('Pausar');
    }
}
