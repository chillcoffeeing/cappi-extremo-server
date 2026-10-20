<?php

namespace Tests\Feature\Api;

use App\Actions\Onboarding\CompleteOnboarding;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * F-062: lado API de la feature de permisos/auditoria (review 2026-10-19:
 * api-A-11, api-A-12) y seguimiento de ver_F-059 obs. 1 (CompleteOnboarding
 * con una orden de inscripcion activa), con tests de compatibilidad
 * (progress/compat-rules-2026-10-19.md).
 */
class BackofficeFollowUpsApiTest extends TestCase
{
    use RefreshDatabase;

    private function storeOrder(User $user, float $total = 60): Order
    {
        return Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [['nombre' => 'Gorra', 'variante' => 'Única', 'qty' => 1, 'precio' => $total]],
            'total' => $total, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);
    }

    private function link(Order $order, string $metodoId, string $metodoNombre, string $ref = 'REF-1'): \Illuminate\Testing\TestResponse
    {
        return $this->post("/api/ordenes/{$order->uuid}/enlazar-pago", [
            'monto' => 20,
            'esCompleto' => false,
            'metodoId' => $metodoId,
            'metodoNombre' => $metodoNombre,
            'referencia' => $ref,
            'comprobante' => UploadedFile::fake()->image('c.jpg'),
        ], ['Accept' => 'application/json']);
    }

    // ── api-A-11: enlazar-pago valida el metodo ──

    public function test_link_payment_with_unknown_method_is_422_and_creates_nothing(): void
    {
        Storage::fake('local');
        $this->seed(PaymentMethodSeeder::class);
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = $this->storeOrder($user);

        $this->link($order, 'no_existe', 'Efectivo en oficina')
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Método de pago no disponible.']);

        $this->assertSame(0, Payment::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_link_payment_with_inactive_method_is_422(): void
    {
        Storage::fake('local');
        $this->seed(PaymentMethodSeeder::class); // met_transferencia_bs esta inactivo
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = $this->storeOrder($user);

        $this->link($order, 'met_transferencia_bs', 'Transferencia Bs')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Método de pago no disponible.');
        $this->assertSame(0, Payment::count());
    }

    /** Compat: un portal viejo sigue mandando `metodoNombre`; se guarda el nombre del catalogo. */
    public function test_old_portal_shape_still_works_and_the_catalog_name_is_stored(): void
    {
        Storage::fake('local');
        $this->seed(PaymentMethodSeeder::class);
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = $this->storeOrder($user);

        $this->link($order, 'met_zelle', 'Nombre inventado por el cliente')->assertOk();

        $payment = $order->payments()->sole();
        $this->assertSame('met_zelle', $payment->method_code);
        $this->assertSame('Zelle', $payment->method_name);
    }

    /** Compat: los pagos ya guardados con un metodo que hoy no existe no se tocan. */
    public function test_existing_payments_with_unknown_method_are_left_untouched(): void
    {
        $user = User::factory()->create();
        $order = $this->storeOrder($user);
        $payment = Payment::create([
            'user_uuid' => $user->uuid, 'order_uuid' => $order->uuid, 'paid_at' => now()->toDateString(),
            'amount' => 20, 'currency' => 'USD', 'method_code' => 'no_existe', 'method_name' => 'Inventado',
            'reference' => 'OLD-1', 'status' => 'PENDIENTE_VERIFICACION', 'receipt_name' => 'r.jpg',
            'idempotency_hash' => hash('sha256', 'old'),
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/pagos')->assertOk();

        $fresh = $payment->fresh();
        $this->assertSame('no_existe', $fresh->method_code);
        $this->assertSame('Inventado', $fresh->method_name);
    }

    // ── api-A-12: 500 alcanzables ──

    public function test_pickup_with_non_string_fields_is_422_not_500(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);

        $this->postJson("/api/participantes/{$participant->uuid}/wizard/step", [
            'stepId' => 'encargado-retiro',
            'data' => ['encargadoRetiro' => ['nombre' => ['x'], 'telefono' => ['y' => 1], 'relacion' => 'PADRE']],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['data.encargadoRetiro.nombre', 'data.encargadoRetiro.telefono']);
    }

    public function test_receipt_whose_file_is_missing_is_404_not_500(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $payment = Payment::create([
            'user_uuid' => $user->uuid, 'paid_at' => now()->toDateString(),
            'amount' => 20, 'currency' => 'USD', 'method_code' => 'met_zelle', 'method_name' => 'Zelle',
            'reference' => 'R-404', 'status' => 'PENDIENTE_VERIFICACION',
            'receipt_path' => 'comprobantes/perdido.jpg', 'receipt_name' => 'perdido.jpg',
            'idempotency_hash' => hash('sha256', 'r404'),
        ]);

        $this->get("/api/pagos/{$payment->uuid}/comprobante", ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_receipt_that_exists_is_still_downloaded(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Storage::disk('local')->put('comprobantes/ok.jpg', 'x');
        $payment = Payment::create([
            'user_uuid' => $user->uuid, 'paid_at' => now()->toDateString(),
            'amount' => 20, 'currency' => 'USD', 'method_code' => 'met_zelle', 'method_name' => 'Zelle',
            'reference' => 'R-OK', 'status' => 'PENDIENTE_VERIFICACION',
            'receipt_path' => 'comprobantes/ok.jpg', 'receipt_name' => 'ok.jpg',
            'idempotency_hash' => hash('sha256', 'rok'),
        ]);

        $this->get("/api/pagos/{$payment->uuid}/comprobante")->assertOk()->assertDownload('ok.jpg');
    }

    // ── Seguimiento ver_F-059 obs. 1 ──

    private function plan(): Plan
    {
        return Plan::create([
            'name' => 'Plan F-062', 'venue' => 'Sede', 'season' => 'Temporada 2026',
            'starts_at' => '2026-12-07', 'ends_at' => '2026-12-11', 'capacity' => 50,
            'price' => 150, 'currency' => 'USD', 'age_min' => 5, 'age_max' => 15, 'status' => 'PUBLICADO',
        ]);
    }

    private function coordinatedMethod(): void
    {
        PaymentMethod::create([
            'code' => 'met_whatsapp', 'type' => 'COORDINADO_REMOTO', 'name' => 'Coordinar por WhatsApp',
            'description' => 'x', 'data' => ['whatsapp' => ['link' => 'https://wa.me/0']], 'active' => true,
        ]);
    }

    /**
     * Familia que completo con Ana (orden 1 x $150, inscripcion PAGADA) y a
     * la que un admin le devolvio onboarding_status=INCOMPLETO; su draft
     * ahora trae a Ana y a un hijo nuevo, Luis.
     *
     * @return array{0: User, 1: \App\Models\OnboardingDraft, 2: Order, 3: Enrollment}
     */
    private function reopenedFamily(Plan $plan): array
    {
        $this->coordinatedMethod();
        $user = User::factory()->create(['onboarding_status' => 'INCOMPLETO']);
        $ana = Participant::factory()->create(['user_uuid' => $user->uuid, 'name' => 'Ana', 'birth_date' => '2018-01-01']);
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [['nombre' => 'Inscripción · Plan F-062', 'variante' => 's', 'qty' => 1, 'precio' => 150]],
            'total' => 150, 'paid' => 150, 'status' => 'PAGADA', 'is_registration' => true,
        ]);
        $enrollment = $ana->enrollment()->create([
            'plan_uuid' => $plan->uuid, 'plan_name' => $plan->name,
            'session_uuid' => 'sesion-original', 'session_name' => 'Original',
            'status' => 'PAGADA', 'plan_type' => 'INDIVIDUAL', 'total_amount' => 150,
            'starts_at' => '2026-12-07', 'ends_at' => '2026-12-11',
        ]);
        $draft = $user->onboardingDrafts()->create([
            'version' => 3,
            'completed_steps' => ['cuenta', 'participantes', 'pago', 'adicionales', 'confirmacion'],
            'data' => [
                'cuenta' => ['planId' => $plan->uuid],
                'participantes' => ['participantes' => [
                    ['nombre' => 'Ana', 'nacimiento' => '2018-01-01'],
                    ['nombre' => 'Luis', 'nacimiento' => '2016-02-02'],
                ]],
                'pago' => ['pago' => ['modalidad' => 'completo', 'metodo' => 'met_whatsapp']],
            ],
            'status' => 'COMPLETADO',
        ]);

        return [$user, $draft, $order, $enrollment];
    }

    public function test_reopened_family_does_not_reopen_existing_enrollments(): void
    {
        $plan = $this->plan();
        [$user, $draft, , $enrollment] = $this->reopenedFamily($plan);

        app(CompleteOnboarding::class)->handle($draft);

        $fresh = $enrollment->fresh();
        $this->assertSame('PAGADA', $fresh->status);
        $this->assertSame('sesion-original', $fresh->session_uuid);
        $this->assertSame('COMPLETADO', $user->fresh()->onboarding_status);
    }

    public function test_reopened_family_new_child_gets_its_own_plaza(): void
    {
        $plan = $this->plan();
        [$user, $draft, $order] = $this->reopenedFamily($plan);

        app(CompleteOnboarding::class)->handle($draft);

        $luis = $user->participants()->where('name', 'Luis')->sole();
        $this->assertNotNull($luis->enrollment);
        $this->assertSame('PENDIENTE_PAGO', $luis->enrollment->status);

        // Plazas de ordenes activas = participantes (antes Luis quedaba sin plaza).
        $covered = $user->orders()->where('is_registration', true)->where('status', '!=', 'CANCELADA')->get()
            ->sum(fn (Order $o) => collect($o->items)->where('precio', '>=', 0)->sum('qty'));
        $this->assertSame(2, (int) $covered);
        $this->assertSame(2, $user->participants()->count());
        // La orden original sigue existiendo y no se cancela.
        $this->assertNotSame('CANCELADA', $order->fresh()->status);
    }

    public function test_reopened_family_completing_twice_is_idempotent(): void
    {
        $plan = $this->plan();
        [$user, $draft] = $this->reopenedFamily($plan);

        app(CompleteOnboarding::class)->handle($draft);
        $orders = $user->orders()->count();
        app(CompleteOnboarding::class)->handle($draft->fresh());

        $this->assertSame($orders, $user->orders()->count());
        $this->assertSame(2, $user->participants()->count());
    }

    /** Compat: el primer onboarding (sin orden previa) sigue igual que antes. */
    public function test_first_onboarding_still_creates_one_order_for_all_children(): void
    {
        $plan = $this->plan();
        $this->coordinatedMethod();
        $user = User::factory()->create();
        $draft = $user->onboardingDrafts()->create([
            'version' => 1,
            'completed_steps' => ['cuenta', 'participantes', 'pago', 'adicionales', 'confirmacion'],
            'data' => [
                'cuenta' => ['planId' => $plan->uuid],
                'participantes' => ['participantes' => [
                    ['nombre' => 'Ana', 'nacimiento' => '2018-01-01'],
                    ['nombre' => 'Luis', 'nacimiento' => '2016-02-02'],
                ]],
                'pago' => ['pago' => ['modalidad' => 'completo', 'metodo' => 'met_whatsapp']],
            ],
            'status' => 'EN_CURSO',
        ]);

        app(CompleteOnboarding::class)->handle($draft);

        $order = $user->orders()->where('is_registration', true)->sole();
        $this->assertSame(2, (int) $order->items[0]['qty']);
        $this->assertSame(2, Enrollment::whereIn('participant_uuid', $user->participants()->pluck('uuid'))->where('status', 'PENDIENTE_PAGO')->count());
    }
}
