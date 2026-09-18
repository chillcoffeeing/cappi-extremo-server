<?php

namespace Tests\Feature\Api;

use App\Actions\Inscriptions\CalculateInscriptionTotal;
use App\Actions\Onboarding\CompleteOnboarding;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiblingDiscountTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(): Plan
    {
        return Plan::create([
            'name' => 'Plan Descuento Test',
            'venue' => 'Sede Test',
            'season' => 'Temporada 2026',
            'starts_at' => '2026-12-07',
            'ends_at' => '2026-12-11',
            'capacity' => 50,
            'price' => 150,
            'currency' => 'USD',
            'age_min' => 5,
            'age_max' => 15,
            'status' => 'PUBLICADO',
        ]);
    }

    private function completeOnboarding(User $user, Plan $plan, array $participants): void
    {
        $draft = $user->onboardingDrafts()->create([
            'version' => 0,
            'completed_steps' => ['cuenta', 'participantes', 'pago', 'adicionales', 'confirmacion'],
            'data' => [
                'cuenta' => [
                    'planId' => $plan->uuid,
                ],
                'participantes' => ['participantes' => $participants],
                'pago' => ['pago' => ['modalidad' => 'completo', 'metodo' => 'Zelle', 'referencia' => 'REF-TEST']],
                'adicionales' => [],
                'confirmacion' => ['aceptado' => true],
            ],
            'status' => 'COMPLETADO',
        ]);

        app(CompleteOnboarding::class)->handle($draft);
    }

    public function test_pricing_single_participant_is_full(): void
    {
        $plan = $this->makePlan();

        $quote = app(CalculateInscriptionTotal::class)->handle(1, 150.0, $plan);

        $this->assertFalse($quote['descuentoAplica']);
        $this->assertSame(0.0, $quote['descuentoTotal']);
        $this->assertSame(150.0, $quote['total']);
    }

    public function test_pricing_two_participants_applies_sibling_discount(): void
    {
        $plan = $this->makePlan();

        $quote = app(CalculateInscriptionTotal::class)->handle(2, 150.0, $plan);

        $this->assertTrue($quote['descuentoAplica']);
        $this->assertSame(20.0, $quote['montoDescuentoPorParticipante']);
        $this->assertSame(40.0, $quote['descuentoTotal']);
        $this->assertSame(260.0, $quote['total']);
    }

    public function test_pricing_respects_custom_minimum_threshold(): void
    {
        $plan = $this->makePlan();
        $plan->update(['sibling_discount_min_participants' => 3]);

        $quote = app(CalculateInscriptionTotal::class)->handle(2, 150.0, $plan);

        $this->assertFalse($quote['descuentoAplica']);
        $this->assertSame(300.0, $quote['total']);
    }

    public function test_pricing_respects_disabled_setting(): void
    {
        $plan = $this->makePlan();
        $plan->update(['sibling_discount_enabled' => false]);

        $quote = app(CalculateInscriptionTotal::class)->handle(3, 150.0, $plan);

        $this->assertFalse($quote['descuentoAplica']);
        $this->assertSame(450.0, $quote['total']);
    }

    public function test_pricing_is_independent_per_plan(): void
    {
        $planA = $this->makePlan();
        $planA->update(['sibling_discount_amount' => 20]);
        $planB = Plan::create([
            'name' => 'Plan Descuento Test B',
            'venue' => 'Sede Test', 'season' => 'Temporada 2026',
            'starts_at' => '2026-12-07', 'ends_at' => '2026-12-11',
            'capacity' => 50, 'price' => 150, 'currency' => 'USD',
            'age_min' => 5, 'age_max' => 15, 'status' => 'PUBLICADO',
            'sibling_discount_amount' => 35,
        ]);

        $quoteA = app(CalculateInscriptionTotal::class)->handle(2, 150.0, $planA);
        $quoteB = app(CalculateInscriptionTotal::class)->handle(2, 150.0, $planB);

        $this->assertSame(20.0, $quoteA['montoDescuentoPorParticipante']);
        $this->assertSame(35.0, $quoteB['montoDescuentoPorParticipante']);
    }

    public function test_onboarding_with_two_participants_creates_discounted_order(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan();

        $this->completeOnboarding($user, $plan, [
            ['nombre' => 'Ana', 'nacimiento' => '2018-01-01'],
            ['nombre' => 'Luis', 'nacimiento' => '2016-02-02'],
        ]);

        $order = $user->orders()->where('is_registration', true)->firstOrFail();
        $this->assertSame(260.0, (float) $order->total);
        $this->assertSame('PENDIENTE_PAGO', $order->status);
        $this->assertCount(2, $order->items);
        $this->assertSame('Descuento hermanos', $order->items[1]['nombre']);
        $this->assertSame(-40.0, (float) $order->items[1]['precio']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'total' => 260, 'is_registration' => true]);
        $this->assertDatabaseHas('enrollments', ['plan_type' => 'HERMANOS', 'sibling_discount' => 20, 'total_amount' => 150]);
    }

    public function test_adding_participant_after_onboarding_creates_new_order_and_recalculates(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan();

        $this->completeOnboarding($user, $plan, [
            ['nombre' => 'Ana', 'nacimiento' => '2018-01-01'],
        ]);

        $inscriptionOrders = $user->orders()->where('is_registration', true)->orderBy('id')->get();
        $this->assertCount(1, $inscriptionOrders);
        $this->assertSame(150.0, (float) $inscriptionOrders[0]->total);

        Sanctum::actingAs($user);
        $this->postJson('/api/participantes', [
            'name' => 'Luis',
            'birthDate' => '2016-02-02',
            'gender' => 'MASCULINO',
        ])->assertOk();

        $updatedOrders = $user->orders()->where('is_registration', true)->orderBy('id')->get();
        $this->assertCount(2, $updatedOrders);
        // Recálculo retroactivo: la orden del onboarding (1 participante) baja de
        // 150 a 130 al crecer la familia a 2, y la nueva alta también sale en 130.
        $this->assertSame(130.0, (float) $updatedOrders[0]->total);
        $this->assertSame(130.0, (float) $updatedOrders[1]->total);
        $this->assertSame(2, \DB::table('enrollments')->where('plan_type', 'HERMANOS')->count());
        $this->assertDatabaseHas('enrollments', ['sibling_discount' => 20]);
    }

    public function test_balance_reflects_discounted_family_total(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan();
        $this->completeOnboarding($user, $plan, [
            ['nombre' => 'Ana', 'nacimiento' => '2018-01-01'],
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/participantes', [
            'name' => 'Luis',
            'birthDate' => '2016-02-02',
            'gender' => 'MASCULINO',
        ])->assertOk();

        $this->getJson('/api/pagos/balance')
            ->assertOk()
            ->assertJsonPath('totalPagar', 260)
            ->assertJsonPath('saldo', 260);
    }

    public function test_config_inscripcion_exposes_pricing_and_discount(): void
    {
        $plan = $this->makePlan();

        $this->getJson('/api/config/plan-price')
            ->assertOk()
            ->assertJson([
                'precio' => (float) $plan->price,
                'moneda' => 'USD',
                'descuentoHermano' => [
                    'activo' => true,
                    'montoPorParticipante' => 20,
                    'minimoParticipantes' => 2,
                ],
            ]);
    }

    public function test_config_inscripcion_without_operative_plan_returns_safe_defaults(): void
    {
        $this->getJson('/api/config/plan-price')
            ->assertOk()
            ->assertJson([
                'precio' => null,
                'moneda' => 'USD',
                'descuentoHermano' => [
                    'activo' => false,
                    'montoPorParticipante' => 0,
                    'minimoParticipantes' => 0,
                ],
            ]);
    }

    /**
     * F-017: migracion de datos `migrate_platform_settings_to_plans` — un
     * plan que ya existia cuando se despliega la migracion hereda el valor
     * VIGENTE del singleton `platform_settings` (no el default de columna),
     * para no cambiar el comportamiento de planes ya publicados.
     */
    public function test_existing_plans_inherit_the_platform_setting_value_on_migration(): void
    {
        \DB::table('platform_settings')->insert([
            'sibling_discount_enabled' => false,
            'sibling_discount_min_participants' => 5,
            'sibling_discount_amount' => 99.5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $plan = $this->makePlan();
        // El plan ya nace con los defaults de columna (true/2/20); simulamos
        // que existia ANTES del despliegue de la migracion de datos.
        $this->assertDatabaseHas('plans', ['id' => $plan->id, 'sibling_discount_amount' => 20]);

        $migration = require base_path('database/migrations/2026_09_18_131730_migrate_platform_settings_to_plans.php');
        $migration->up();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'sibling_discount_enabled' => false,
            'sibling_discount_min_participants' => 5,
            'sibling_discount_amount' => 99.5,
        ]);
    }

    public function test_validation_messages_are_in_spanish(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/ordenes', [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'El campo ítems es obligatorio.')
            ->assertJsonPath('errors.items.0', 'El campo ítems es obligatorio.');
    }
}