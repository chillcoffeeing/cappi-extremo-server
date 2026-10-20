<?php

namespace Tests\Feature\Api;

use App\Actions\Inscriptions\RegisterParticipantInscription;
use App\Actions\Onboarding\CompleteOnboarding;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * F-059: integridad de inscripciones y saldos (review 2026-10-19: api-A-3,
 * A-4, A-5, B-1, B-5; fe-A-10, fe-A-11) + compatibilidad con los datos
 * reales (progress/compat-rules-2026-10-19.md).
 */
class InscriptionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(array $overrides = []): Plan
    {
        return Plan::create([
            'name' => 'Plan F-059',
            'venue' => 'Sede',
            'season' => 'Temporada 2026',
            'starts_at' => '2026-12-07',
            'ends_at' => '2026-12-11',
            'capacity' => 50,
            'price' => 150,
            'currency' => 'USD',
            'age_min' => 5,
            'age_max' => 15,
            'status' => 'PUBLICADO',
            ...$overrides,
        ]);
    }

    private function coordinatedMethod(): PaymentMethod
    {
        return PaymentMethod::create([
            'code' => 'met_whatsapp',
            'type' => 'COORDINADO_REMOTO',
            'name' => 'Coordinar por WhatsApp',
            'description' => 'Coordina tu primer pago por WhatsApp.',
            'data' => ['whatsapp' => ['link' => 'https://wa.me/0']],
            'active' => true,
        ]);
    }

    /** Guarda los 5 pasos del portal actual (formato nuevo: `pago.pago`). */
    private function saveAllSteps(string $draftId, Plan $plan, array $participants, array $pago): void
    {
        $steps = [
            'cuenta' => ['planId' => $plan->uuid, 'representante' => ['nombre' => 'Rep']],
            'participantes' => ['participantes' => $participants],
            'pago' => ['pago' => $pago],
            'confirmacion' => ['confirmacion' => true, 'adicionales' => []],
            'adicionales' => ['items' => []],
        ];

        foreach ($steps as $stepId => $data) {
            $this->postJson("/api/onboarding/{$draftId}/step", ['stepId' => $stepId, 'data' => $data])->assertOk();
        }
    }

    /** Usuario que completo el onboarding con 1 hijo (orden 1 x $150). */
    private function completedFamily(Plan $plan): array
    {
        $this->coordinatedMethod();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $draftId = $user->uuid;

        $this->saveAllSteps($draftId, $plan, [['nombre' => 'Ana', 'nacimiento' => '2018-01-01']], [
            'modalidad' => 'completo', 'metodo' => 'met_whatsapp',
        ]);
        $this->postJson("/api/onboarding/{$draftId}/complete")
            ->assertOk()
            ->assertJsonPath('status', 'COMPLETADO');

        return [$user->fresh(), $draftId];
    }

    // ── Compatibilidad: un onboarding legitimo en curso se completa ──

    public function test_onboarding_in_progress_with_current_portal_format_still_completes(): void
    {
        $plan = $this->makePlan();
        $this->coordinatedMethod();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->saveAllSteps($user->uuid, $plan, [
            ['nombre' => 'Ana', 'nacimiento' => '2018-01-01'],
            ['nombre' => 'Luis', 'nacimiento' => '2016-02-02'],
        ], ['modalidad' => 'cuotas', 'montoAbonar' => '20', 'metodo' => 'met_whatsapp']);

        $this->postJson("/api/onboarding/{$user->uuid}/complete")
            ->assertOk()
            ->assertJsonPath('status', 'COMPLETADO');

        $this->assertSame('COMPLETADO', $user->fresh()->onboarding_status);
        $this->assertSame(2, $user->participants()->count());
        $order = $user->orders()->where('is_registration', true)->sole();
        $this->assertSame(260.0, (float) $order->total);
        $this->assertSame(20.0, (float) Payment::where('order_uuid', $order->uuid)->sole()->amount);
    }

    /**
     * Draft guardado con el formato viejo (pago plano, metodo por NOMBRE y
     * sin `adicionales` en `data`), ya en LISTO_PARA_CONFIRMAR, de un
     * usuario que todavia no completo: se completa igual.
     */
    public function test_old_format_draft_saved_before_the_deploy_still_completes(): void
    {
        $plan = $this->makePlan();
        $user = User::factory()->create();
        $draft = $user->onboardingDrafts()->create([
            'version' => 4,
            'completed_steps' => ['cuenta', 'participantes', 'pago', 'adicionales', 'confirmacion'],
            'data' => [
                'cuenta' => ['planId' => $plan->uuid],
                'participantes' => ['participantes' => [['nombre' => 'Yessi', 'nacimiento' => '2018-09-10']]],
                'pago' => ['modalidad' => 'completo', 'metodo' => 'Zelle', 'referencia' => 'REF-OLD'],
            ],
            'status' => 'LISTO_PARA_CONFIRMAR',
        ]);
        Sanctum::actingAs($user);
        Storage::fake('local');

        $this->post("/api/onboarding/{$draft->uuid}/complete", [
            'comprobante' => UploadedFile::fake()->image('comprobante.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'COMPLETADO');

        $this->assertSame('COMPLETADO', $user->fresh()->onboarding_status);
        $this->assertDatabaseHas('payments', ['user_uuid' => $user->uuid, 'reference' => 'REF-OLD', 'amount' => 150]);
    }

    /**
     * Un draft marcado COMPLETADO cuyo usuario sigue INCOMPLETO (estado
     * inconsistente heredado) no deja al representante bloqueado: se completa.
     */
    public function test_draft_marked_completed_of_a_user_still_incomplete_can_finish(): void
    {
        $plan = $this->makePlan();
        $user = User::factory()->create();
        $draft = $user->onboardingDrafts()->create([
            'version' => 1,
            'completed_steps' => ['cuenta', 'participantes', 'pago', 'adicionales', 'confirmacion'],
            'data' => [
                'cuenta' => ['planId' => $plan->uuid],
                'participantes' => ['participantes' => [['nombre' => 'Ana', 'nacimiento' => '2018-01-01']]],
                'pago' => ['pago' => ['modalidad' => 'completo', 'metodo' => 'met_whatsapp']],
            ],
            'status' => 'COMPLETADO',
        ]);
        $this->coordinatedMethod();

        app(CompleteOnboarding::class)->handle($draft);

        $this->assertSame('COMPLETADO', $user->fresh()->onboarding_status);
        $this->assertSame(1, $user->orders()->where('is_registration', true)->count());
    }

    // ── api-A-3 / B-1: un usuario COMPLETADO no reabre el onboarding ──

    public function test_completed_user_cannot_open_a_new_draft(): void
    {
        $plan = $this->makePlan();
        [$user] = $this->completedFamily($plan);
        $newDraftId = (string) Str::uuid();

        $this->getJson("/api/onboarding/{$newDraftId}")
            ->assertStatus(422)
            ->assertJsonPath('message', CompleteOnboarding::ALREADY_COMPLETED_MESSAGE);
        $this->postJson("/api/onboarding/{$newDraftId}/step", [
            'stepId' => 'participantes',
            'data' => ['participantes' => [['nombre' => 'Luis', 'nacimiento' => '2016-02-02']]],
        ])->assertStatus(422)->assertJsonPath('message', CompleteOnboarding::ALREADY_COMPLETED_MESSAGE);
        $this->postJson("/api/onboarding/{$newDraftId}/complete")->assertStatus(422);

        $this->assertDatabaseMissing('onboarding_drafts', ['uuid' => $newDraftId]);
        $this->assertSame(1, $user->participants()->count());
    }

    /** Caso 1 del review: otro draft (ya existente) con 3 hijos y cuotas 0. */
    public function test_completed_user_cannot_complete_another_existing_draft(): void
    {
        $plan = $this->makePlan();
        [$user] = $this->completedFamily($plan);
        $other = $user->onboardingDrafts()->create([
            'version' => 3,
            'completed_steps' => ['cuenta', 'participantes', 'pago', 'adicionales', 'confirmacion'],
            'data' => [
                'cuenta' => ['planId' => $plan->uuid],
                'participantes' => ['participantes' => [
                    ['nombre' => 'B', 'nacimiento' => '2017-01-01'],
                    ['nombre' => 'C', 'nacimiento' => '2016-01-01'],
                    ['nombre' => 'D', 'nacimiento' => '2015-01-01'],
                ]],
                'pago' => ['pago' => ['modalidad' => 'cuotas', 'montoAbonar' => 0, 'metodo' => 'met_whatsapp']],
            ],
            'status' => 'LISTO_PARA_CONFIRMAR',
        ]);

        $this->postJson("/api/onboarding/{$other->uuid}/step", [
            'stepId' => 'confirmacion', 'data' => ['confirmacion' => true],
        ])->assertStatus(422)->assertJsonPath('message', CompleteOnboarding::ALREADY_COMPLETED_MESSAGE);
        $this->postJson("/api/onboarding/{$other->uuid}/complete")
            ->assertStatus(422)
            ->assertJsonPath('message', CompleteOnboarding::ALREADY_COMPLETED_MESSAGE)
            ->assertJsonValidationErrors('onboarding');

        $this->assertSame(1, $user->participants()->count());
        $this->assertSame(1, $user->participants()->has('enrollment')->count());
        $this->assertSame('LISTO_PARA_CONFIRMAR', $other->fresh()->status);
    }

    /** Caso 2 del review: el MISMO draft COMPLETADO no se reabre ni re-inscribe. */
    public function test_same_completed_draft_is_idempotent_and_does_not_reopen(): void
    {
        $plan = $this->makePlan();
        [$user, $draftId] = $this->completedFamily($plan);
        $order = $user->orders()->where('is_registration', true)->sole();
        $version = $user->onboardingDrafts()->where('uuid', $draftId)->value('version');

        $this->postJson("/api/onboarding/{$draftId}/step", [
            'stepId' => 'participantes',
            'data' => ['participantes' => [
                ['nombre' => 'Ana', 'nacimiento' => '2018-01-01'],
                ['nombre' => 'Extra', 'nacimiento' => '2016-02-02'],
            ]],
        ])->assertOk()->assertJsonPath('status', 'COMPLETADO');

        // Reintento del portal (doble clic / error de red): 200 sin crear nada.
        $this->postJson("/api/onboarding/{$draftId}/complete")
            ->assertOk()
            ->assertJsonPath('status', 'COMPLETADO');
        $this->postJson("/api/onboarding/{$draftId}/complete")->assertOk();

        $this->assertSame(1, $user->participants()->count());
        $this->assertSame(1, $user->orders()->count());
        $this->assertSame(1, Payment::where('user_uuid', $user->uuid)->count());
        $this->assertSame((float) $order->total, (float) $order->fresh()->total);
        $this->assertSame($version, $user->onboardingDrafts()->where('uuid', $draftId)->value('version'));
        $this->assertSame(1, count($user->onboardingDrafts()->where('uuid', $draftId)->value('data')['participantes']['participantes']));
        // El draft COMPLETADO se sigue pudiendo leer.
        $this->getJson("/api/onboarding/{$draftId}")->assertOk();
    }

    // ── api-B-5: minimo de cuotas en el servidor ──

    public function test_installment_below_minimum_is_rejected_with_spanish_message(): void
    {
        $plan = $this->makePlan();
        $this->coordinatedMethod();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->saveAllSteps($user->uuid, $plan, [['nombre' => 'Ana', 'nacimiento' => '2018-01-01']], [
            'modalidad' => 'cuotas', 'montoAbonar' => '10', 'metodo' => 'met_whatsapp',
        ]);

        $this->postJson("/api/onboarding/{$user->uuid}/complete")
            ->assertStatus(422)
            ->assertJsonValidationErrors('montoAbonar')
            ->assertJsonPath('message', 'El abono mínimo es $20.00. Vuelve al paso Pago y ajusta el monto.');

        $this->assertSame(0, $user->participants()->count());
        $this->assertSame(0, $user->orders()->count());
        $this->assertSame('INCOMPLETO', $user->fresh()->onboarding_status);

        // Corrige el monto en el paso Pago y completa.
        $this->postJson("/api/onboarding/{$user->uuid}/step", [
            'stepId' => 'pago',
            'data' => ['pago' => ['modalidad' => 'cuotas', 'montoAbonar' => '20', 'metodo' => 'met_whatsapp']],
        ])->assertOk();
        $this->postJson("/api/onboarding/{$user->uuid}/complete")->assertOk()->assertJsonPath('status', 'COMPLETADO');
    }

    public function test_installment_minimum_is_capped_at_the_total(): void
    {
        $plan = $this->makePlan(['price' => 15]);
        $this->coordinatedMethod();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->saveAllSteps($user->uuid, $plan, [['nombre' => 'Ana', 'nacimiento' => '2018-01-01']], [
            'modalidad' => 'cuotas', 'montoAbonar' => '15', 'metodo' => 'met_whatsapp',
        ]);

        $this->postJson("/api/onboarding/{$user->uuid}/complete")->assertOk();
    }

    // ── F-077: sin alta de hijos desde el portal ──

    public function test_portal_can_no_longer_create_participants(): void
    {
        $user = User::factory()->create(['onboarding_status' => 'COMPLETADO']);
        Sanctum::actingAs($user);

        $this->postJson('/api/participantes', ['name' => 'C', 'birthDate' => '2016-05-05', 'gender' => 'FEMENINO'])
            ->assertStatus(405);
        $this->assertSame(0, Participant::count());
    }

    // ── api-A-5: plazas de ordenes CANCELADA ──

    public function test_cancelled_registration_order_does_not_cover_a_new_participant(): void
    {
        $this->makePlan();
        $user = User::factory()->create();
        $user->forceFill(['onboarding_status' => 'COMPLETADO'])->save();
        $b = Participant::factory()->create(['user_uuid' => $user->uuid, 'name' => 'B']);
        $items = fn () => [['nombre' => 'Inscripción · Plan F-059', 'variante' => 's', 'qty' => 1, 'precio' => 150]];
        $cancelled = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(), 'items' => $items(),
            'total' => 150, 'paid' => 0, 'status' => 'CANCELADA', 'is_registration' => true,
        ]);
        $pending = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(), 'items' => $items(),
            'total' => 150, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => true,
        ]);
        // F-077: sin POST /participantes; misma alta que hace CompleteOnboarding.
        $c = Participant::factory()->create(['user_uuid' => $user->uuid, 'name' => 'C', 'birth_date' => '2016-05-05', 'gender' => 'FEMENINO', 'data_completed' => false]);
        app(RegisterParticipantInscription::class)->handle($c);

        // C tiene su propia orden (antes: "covered 2 >= 2" y quedaba gratis).
        $active = $user->orders()->where('is_registration', true)->where('status', '!=', 'CANCELADA')->get();
        $this->assertCount(2, $active);
        // Las dos activas se re-precian con el descuento hermanos (regla F-002)...
        $this->assertSame([130.0, 130.0], $active->map(fn ($o) => (float) $o->total)->all());
        // ...y la CANCELADA queda exactamente como estaba.
        $cancelled->refresh();
        $this->assertSame('CANCELADA', $cancelled->status);
        $this->assertSame(150.0, (float) $cancelled->total);
        $this->assertSame($items(), $cancelled->items);
        $this->assertNotNull($pending->fresh());
        $this->assertNotNull($b->fresh());
    }

    // ── api-A-4 / fe-A-11: balance sin ordenes CANCELADA ──

    public function test_balance_excludes_cancelled_orders_and_their_payments(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $cancelled = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [['nombre' => 'Gorra', 'variante' => '', 'qty' => 1, 'precio' => 80]],
            'total' => 80, 'paid' => 30, 'status' => 'CANCELADA', 'is_registration' => false,
        ]);
        $open = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [['nombre' => 'Franela', 'variante' => 'M', 'qty' => 1, 'precio' => 50]],
            'total' => 50, 'paid' => 10, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);
        $pay = fn (?string $orderUuid, float $amount, string $ref) => Payment::create([
            'user_uuid' => $user->uuid, 'order_uuid' => $orderUuid, 'paid_at' => now()->toDateString(),
            'amount' => $amount, 'currency' => 'USD', 'method_code' => 'met_zelle', 'method_name' => 'Zelle',
            'reference' => $ref, 'concept' => 'x', 'status' => 'APROBADO', 'receipt_name' => 'c.jpg', 'idempotency_hash' => hash('sha256', $ref),
        ]);
        $pay($cancelled->uuid, 30, 'R1');
        $pay($open->uuid, 10, 'R2');
        $pay(null, 5, 'R3'); // reporte suelto (POST /pagos) sigue contando

        $this->getJson('/api/pagos/balance')
            ->assertOk()
            ->assertJsonPath('totalPagar', 50)
            ->assertJsonPath('totalAbonado', 15)
            ->assertJsonPath('saldo', 35);

        // Solo lectura: la orden cancelada no cambia.
        $this->assertSame(80.0, (float) $cancelled->fresh()->total);
        $this->assertSame(30.0, (float) $cancelled->fresh()->paid);
        $this->assertSame('CANCELADA', $cancelled->fresh()->status);
    }

    public function test_balance_with_only_a_cancelled_order_is_zero(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [['nombre' => 'Gorra', 'variante' => '', 'qty' => 1, 'precio' => 80]],
            'total' => 80, 'paid' => 0, 'status' => 'CANCELADA', 'is_registration' => false,
        ]);

        $this->getJson('/api/pagos/balance')
            ->assertOk()
            ->assertJsonPath('totalPagar', 0)
            ->assertJsonPath('saldo', 0)
            ->assertJsonStructure(['totalPagar', 'totalAbonado', 'saldo', 'proximaFechaLimite', 'moneda', 'primerPagoCoordinado']);
    }

    // ── Compatibilidad: las ordenes viejas mantienen total y estado ──

    public function test_existing_orders_keep_their_total_and_status(): void
    {
        $plan = $this->makePlan();
        [$user, $draftId] = $this->completedFamily($plan);
        $registration = $user->orders()->where('is_registration', true)->sole();
        // Simula datos reales previos al despliegue: inscripcion ya pagada,
        // pedido de tienda pendiente y un pedido cancelado (re-preciado antes).
        $registration->update(['paid' => 150, 'status' => 'PAGADA']);
        $store = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [['nombre' => 'Franela', 'variante' => 'M', 'qty' => 2, 'precio' => 12.5]],
            'total' => 25, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);
        $oldCancelled = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [['nombre' => 'Inscripción · Plan F-059', 'variante' => 's', 'qty' => 1, 'precio' => 150]],
            'total' => 150, 'paid' => 0, 'status' => 'CANCELADA', 'is_registration' => true,
        ]);
        $snapshot = fn () => Order::orderBy('id')->get()->map(fn (Order $o) => [
            $o->uuid, (float) $o->total, (float) $o->paid, $o->status, $o->items,
        ])->all();
        $before = $snapshot();

        // Lecturas y operaciones bloqueadas no tocan nada.
        $this->getJson('/api/pagos/balance')->assertOk();
        $this->postJson("/api/onboarding/{$draftId}/complete")->assertOk();
        $this->postJson('/api/onboarding/'.Str::uuid().'/complete')->assertStatus(422);

        $this->assertSame($before, $snapshot());
        $this->assertSame('PAGADA', $registration->fresh()->status);
        $this->assertSame(25.0, (float) $store->fresh()->total);
        $this->assertSame(150.0, (float) $oldCancelled->fresh()->total);
    }

    // ── fe-A-10 / fe-C-4: sin plan operativo ──

    public function test_active_plan_without_operative_plan_returns_204(): void
    {
        $this->makePlan(['status' => 'BORRADOR']);
        Sanctum::actingAs(User::factory()->create());

        $this->get('/api/planes/activo', ['Accept' => 'application/json'])->assertNoContent();
    }
}
