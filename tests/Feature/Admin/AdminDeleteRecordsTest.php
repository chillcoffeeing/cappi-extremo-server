<?php

namespace Tests\Feature\Admin;

use App\Actions\Admin\DeleteEnrollment;
use App\Actions\Admin\DeleteParticipant;
use App\Actions\Admin\DeleteRepresentative;
use App\Actions\Onboarding\CompleteOnboarding;
use App\Actions\Orders\CancelOrder;
use App\Actions\Payments\ApprovePayment;
use App\Actions\Payments\RejectPayment;
use App\Models\AdminUser;
use App\Models\Enrollment;
use App\Models\OnboardingDraft;
use App\Models\Order;
use App\Models\Participant;
use App\Models\ParticipantCorrectionRequest;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PortalAuthToken;
use App\Models\RefreshToken;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * F-053: borrado administrativo (solo SUPER_ADMIN, `records.delete`).
 * Se conserva la evidencia financiera (ordenes, pagos, comprobantes,
 * activity_log); lo demas se borra fisicamente.
 */
class AdminDeleteRecordsTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(): Plan
    {
        return Plan::create([
            'name' => 'Plan Borrado Test', 'venue' => 'Sede Test', 'season' => 'Temporada 2026',
            'starts_at' => '2026-12-07', 'ends_at' => '2026-12-11',
            'capacity' => 50, 'price' => 150, 'currency' => 'USD',
            'age_min' => 5, 'age_max' => 15, 'status' => 'PUBLICADO',
        ]);
    }

    private function makeEnrollment(Participant $participant, ?Plan $plan = null): Enrollment
    {
        return Enrollment::create([
            'participant_uuid' => $participant->uuid,
            'plan_uuid' => $plan?->uuid,
            'plan_name' => 'Plan Borrado Test', 'session_uuid' => (string) Str::uuid(), 'session_name' => 'Vespertina',
            'status' => 'PENDIENTE_PAGO', 'plan_type' => 'INDIVIDUAL',
            'total_amount' => 150, 'starts_at' => '2026-12-07', 'ends_at' => '2026-12-11',
        ]);
    }

    private function makeOrder(?User $user, float $total, float $paid, string $status): Order
    {
        return Order::create([
            'user_uuid' => $user?->uuid, 'ordered_at' => now()->toDateString(), 'items' => [],
            'total' => $total, 'paid' => $paid, 'status' => $status, 'is_registration' => false,
        ]);
    }

    private function makePayment(?User $user, Order $order, float $amount, string $status, ?string $receiptPath = null): Payment
    {
        return Payment::create([
            'user_uuid' => $user?->uuid, 'order_uuid' => $order->uuid, 'paid_at' => now()->toDateString(),
            'amount' => $amount, 'currency' => 'USD', 'method_code' => 'met_zelle', 'method_name' => 'Zelle',
            'reference' => 'TX-'.Str::random(6), 'status' => $status, 'receipt_path' => $receiptPath, 'receipt_name' => 'r.jpg',
            'idempotency_hash' => hash('sha256', Str::uuid()),
        ]);
    }

    /**
     * Representante con todo lo que puede colgar de el.
     *
     * @return array<string, mixed>
     */
    private function makeFamily(): array
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('profiles/rep.jpg', 'foto');
        Storage::disk('public')->put('participants/ana.jpg', 'foto');
        Storage::disk('local')->put('receipts/comprobante.jpg', 'comprobante');

        $user = User::factory()->create([
            'name' => 'Maria', 'last_name' => 'Perez', 'email' => 'maria@example.com',
            'identification' => 'V-12345678', 'photo_url' => 'profiles/rep.jpg',
        ]);
        $ana = Participant::factory()->create(['user_uuid' => $user->uuid, 'name' => 'Ana', 'photo_url' => 'participants/ana.jpg']);
        $luis = Participant::factory()->create(['user_uuid' => $user->uuid, 'name' => 'Luis']);
        $this->makeEnrollment($ana);
        $this->makeEnrollment($luis);
        ParticipantCorrectionRequest::create(['participant_uuid' => $ana->uuid, 'section' => 'salud', 'message' => 'Corregir alergias']);
        OnboardingDraft::create(['user_uuid' => $user->uuid, 'status' => 'COMPLETADO']);
        RefreshToken::create(['user_uuid' => $user->uuid, 'token_hash' => hash('sha256', 'rt-'.Str::random()), 'expires_at' => now()->addDay()]);
        PortalAuthToken::create(['user_uuid' => $user->uuid, 'email' => $user->email, 'type' => 'RESET', 'token_hash' => hash('sha256', 'pat-'.Str::random()), 'expires_at' => now()->addDay()]);
        $user->createToken('portal');

        $pendingOrder = $this->makeOrder($user, total: 300, paid: 0, status: 'PENDIENTE_PAGO');
        $paidOrder = $this->makeOrder($user, total: 50, paid: 50, status: 'PAGADA');
        $pendingPayment = $this->makePayment($user, $pendingOrder, 100, 'PENDIENTE_VERIFICACION', 'receipts/comprobante.jpg');
        $approvedPayment = $this->makePayment($user, $paidOrder, 50, 'APROBADO');

        return compact('user', 'ana', 'luis', 'pendingOrder', 'paidOrder', 'pendingPayment', 'approvedPayment');
    }

    public function test_delete_representative_removes_everything_except_financial_evidence(): void
    {
        $f = $this->makeFamily();
        $user = $f['user'];

        // Otro representante: no debe verse afectado.
        $other = User::factory()->create();
        $otherParticipant = Participant::factory()->create(['user_uuid' => $other->uuid]);
        $otherOrder = $this->makeOrder($other, 10, 0, 'PENDIENTE_PAGO');

        $activityBefore = DB::table('activity_log')->count();

        $result = app(DeleteRepresentative::class)->handle($user);

        $this->assertSame([
            'enrollments' => 2, 'correctionRequests' => 1, 'participants' => 2, 'onboardingDrafts' => 1,
            'refreshTokens' => 1, 'portalAuthTokens' => 1, 'accessTokens' => 1, 'photos' => 2,
        ], $result['deleted']);
        $this->assertSame([
            'orders' => 2, 'payments' => 2, 'pendingVerificationPayments' => 1, 'ordersWithBalance' => 1,
        ], $result['kept']);

        // Borrado.
        $this->assertNull(User::find($user->getKey()));
        $this->assertSame(0, Participant::where('user_uuid', $user->uuid)->count());
        $this->assertDatabaseMissing('participants', ['uuid' => $f['ana']->uuid]);
        $this->assertDatabaseMissing('participants', ['uuid' => $f['luis']->uuid]);
        $this->assertSame(0, Enrollment::whereIn('participant_uuid', [$f['ana']->uuid, $f['luis']->uuid])->count());
        $this->assertSame(0, ParticipantCorrectionRequest::count());
        $this->assertSame(0, OnboardingDraft::count());
        $this->assertSame(0, RefreshToken::count());
        $this->assertSame(0, PortalAuthToken::count());
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Conservado: ordenes y pagos sin representante, con snapshot y la relacion orden<->pago intacta.
        $expectedSnapshot = ['name' => 'Maria', 'lastName' => 'Perez', 'email' => 'maria@example.com', 'identification' => 'V-12345678'];
        foreach ([$f['pendingOrder'], $f['paidOrder']] as $order) {
            $order = $order->fresh();
            $this->assertNotNull($order);
            $this->assertNull($order->user_uuid);
            $this->assertSame($expectedSnapshot, array_intersect_key($order->representative_snapshot, $expectedSnapshot));
            $this->assertArrayHasKey('deletedAt', $order->representative_snapshot);
        }
        foreach ([[$f['pendingPayment'], $f['pendingOrder']], [$f['approvedPayment'], $f['paidOrder']]] as [$payment, $order]) {
            $payment = $payment->fresh();
            $this->assertNotNull($payment);
            $this->assertNull($payment->user_uuid);
            $this->assertSame($order->uuid, $payment->order_uuid);
            $this->assertSame($expectedSnapshot, array_intersect_key($payment->representative_snapshot, $expectedSnapshot));
        }
        $this->assertSame($activityBefore, DB::table('activity_log')->count());

        // Archivos: fotos fuera, comprobante conservado.
        Storage::disk('public')->assertMissing('profiles/rep.jpg');
        Storage::disk('public')->assertMissing('participants/ana.jpg');
        Storage::disk('local')->assertExists('receipts/comprobante.jpg');

        // El otro representante sigue intacto.
        $this->assertNotNull($other->fresh());
        $this->assertNotNull($otherParticipant->fresh());
        $this->assertSame($other->uuid, $otherOrder->fresh()->user_uuid);
        $this->assertNull($otherOrder->fresh()->representative_snapshot);
    }

    public function test_impact_reports_the_same_counts_without_deleting_anything(): void
    {
        $f = $this->makeFamily();

        $impact = app(DeleteRepresentative::class)->impact($f['user']);

        $this->assertSame(2, $impact['deleted']['participants']);
        $this->assertSame(2, $impact['deleted']['enrollments']);
        $this->assertSame(2, $impact['deleted']['photos']);
        $this->assertSame(['orders' => 2, 'payments' => 2, 'pendingVerificationPayments' => 1, 'ordersWithBalance' => 1], $impact['kept']);
        $this->assertNotNull($f['user']->fresh());
        $this->assertSame(2, Participant::count());
        Storage::disk('public')->assertExists('profiles/rep.jpg');
    }

    public function test_orders_and_payments_without_representative_can_still_be_approved_rejected_and_cancelled(): void
    {
        $f = $this->makeFamily();
        $secondPending = $this->makePayment($f['user'], $f['pendingOrder'], 50, 'PENDIENTE_VERIFICACION');
        $cancellable = $this->makeOrder($f['user'], 80, 0, 'PENDIENTE_PAGO');
        app(DeleteRepresentative::class)->handle($f['user']);
        $admin = AdminUser::factory()->create();

        $approved = app(ApprovePayment::class)->handle($f['pendingPayment']->fresh(), $admin);
        $this->assertSame('APROBADO', $approved->status);
        $this->assertEquals(100, (float) $f['pendingOrder']->fresh()->paid);
        $this->assertSame('PENDIENTE_PAGO', $f['pendingOrder']->fresh()->status);

        $rejected = app(RejectPayment::class)->handle($secondPending->fresh(), 'Referencia invalida', $admin);
        $this->assertSame('RECHAZADO', $rejected->status);

        $cancelled = app(CancelOrder::class)->handle($cancellable->fresh(), 'Representante eliminado', $admin);
        $this->assertSame('CANCELADA', $cancelled->status);
        $this->assertNull($cancelled->user_uuid);
    }

    public function test_a_rollback_keeps_rows_and_files(): void
    {
        $f = $this->makeFamily();
        User::deleting(function (): void {
            throw new RuntimeException('fallo simulado');
        });

        try {
            app(DeleteRepresentative::class)->handle($f['user']);
            $this->fail('Se esperaba la excepcion simulada.');
        } catch (RuntimeException $exception) {
            $this->assertSame('fallo simulado', $exception->getMessage());
        }

        $this->assertNotNull($f['user']->fresh());
        $this->assertSame(2, Participant::count());
        $this->assertSame(2, Enrollment::count());
        $this->assertSame($f['user']->uuid, $f['pendingOrder']->fresh()->user_uuid);
        $this->assertNull($f['pendingOrder']->fresh()->representative_snapshot);
        Storage::disk('public')->assertExists('profiles/rep.jpg');
        Storage::disk('public')->assertExists('participants/ana.jpg');
    }

    public function test_fk_cascade_is_the_safety_net_when_a_user_row_is_deleted_directly(): void
    {
        $f = $this->makeFamily();

        $f['user']->delete();

        $this->assertSame(0, Participant::count());
        $this->assertSame(0, Enrollment::count());
        $this->assertSame(0, ParticipantCorrectionRequest::count());
        $this->assertSame(0, OnboardingDraft::count());
        $this->assertSame(0, RefreshToken::count());
        $this->assertSame(0, PortalAuthToken::count());
        // Ordenes y pagos siguen (nullOnDelete) y unidos entre si.
        $this->assertSame(2, Order::whereNull('user_uuid')->count());
        $this->assertSame(2, Payment::whereNull('user_uuid')->count());
        $this->assertSame($f['pendingOrder']->uuid, $f['pendingPayment']->fresh()->order_uuid);
    }

    public function test_delete_participant_removes_its_records_and_recalculates_sibling_discount(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $plan = $this->makePlan();
        $draft = $user->onboardingDrafts()->create([
            'version' => 0,
            'completed_steps' => ['cuenta', 'participantes', 'pago', 'adicionales', 'confirmacion'],
            'data' => [
                'cuenta' => ['planId' => $plan->uuid],
                'participantes' => ['participantes' => [
                    ['nombre' => 'Ana', 'nacimiento' => '2018-01-01'],
                    ['nombre' => 'Luis', 'nacimiento' => '2016-02-02'],
                ]],
                'pago' => ['pago' => ['modalidad' => 'completo', 'metodo' => 'Zelle', 'referencia' => 'REF-DEL']],
                'adicionales' => [],
                'confirmacion' => ['aceptado' => true],
            ],
            'status' => 'COMPLETADO',
        ]);
        Storage::fake('local');
        app(CompleteOnboarding::class)->handle($draft, UploadedFile::fake()->image('comprobante.jpg'));

        $order = $user->orders()->where('is_registration', true)->firstOrFail();
        $this->assertSame(260.0, (float) $order->total);
        $ordersBefore = Order::count();

        $ana = $user->participants()->where('name', 'Ana')->firstOrFail();
        $luis = $user->participants()->where('name', 'Luis')->firstOrFail();
        Storage::disk('public')->put('participants/ana.jpg', 'foto');
        $ana->update(['photo_url' => 'participants/ana.jpg']);
        ParticipantCorrectionRequest::create(['participant_uuid' => $ana->uuid, 'section' => 'salud', 'message' => 'x']);

        $result = app(DeleteParticipant::class)->handle($ana);

        $this->assertSame(['enrollments' => 1, 'correctionRequests' => 1, 'photos' => 1], $result);
        $this->assertNull($ana->fresh());
        $this->assertSame(0, Enrollment::where('participant_uuid', $ana->uuid)->count());
        $this->assertSame(0, ParticipantCorrectionRequest::count());
        Storage::disk('public')->assertMissing('participants/ana.jpg');

        // La orden no cambia de cantidad (sigue cubriendo 2 plazas) pero pierde
        // el descuento hermanos: la familia ahora tiene 1 participante.
        $this->assertSame($ordersBefore, Order::count());
        $order->refresh();
        $this->assertCount(1, $order->items);
        $this->assertSame(2, (int) $order->items[0]['qty']);
        $this->assertSame(300.0, (float) $order->total);
        $this->assertDatabaseHas('enrollments', ['participant_uuid' => $luis->uuid, 'plan_type' => 'INDIVIDUAL', 'sibling_discount' => 0]);
        $this->assertNotNull($user->fresh());
    }

    public function test_delete_enrollment_only_removes_the_enrollment(): void
    {
        $user = User::factory()->create();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);
        $enrollment = $this->makeEnrollment($participant);
        $order = $this->makeOrder($user, 150, 0, 'PENDIENTE_PAGO');

        $this->assertSame(['enrollments' => 1], app(DeleteEnrollment::class)->handle($enrollment));

        $this->assertNull($enrollment->fresh());
        $this->assertNotNull($participant->fresh());
        $this->assertEquals(150, (float) $order->fresh()->total);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function rolesProvider(): array
    {
        return [
            'SUPER_ADMIN' => ['SUPER_ADMIN', true],
            'OPERACIONES' => ['OPERACIONES', false],
            'FINANZAS' => ['FINANZAS', false],
            'SOPORTE' => ['SOPORTE', false],
            'LECTURA' => ['LECTURA', false],
        ];
    }

    #[DataProvider('rolesProvider')]
    public function test_only_super_admin_passes_the_delete_policies(string $role, bool $expected): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole($role);

        $user = User::factory()->create();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);
        $enrollment = $this->makeEnrollment($participant);
        $draft = OnboardingDraft::create(['user_uuid' => $user->uuid, 'status' => 'INCOMPLETO']);
        $correction = ParticipantCorrectionRequest::create(['participant_uuid' => $participant->uuid, 'section' => 'salud', 'message' => 'x']);
        $order = $this->makeOrder($user, 10, 0, 'PENDIENTE_PAGO');
        $payment = $this->makePayment($user, $order, 10, 'PENDIENTE_VERIFICACION');

        foreach ([$user, $participant, $enrollment, $draft, $correction] as $record) {
            $this->assertSame($expected, $admin->can('delete', $record), "{$role} delete ".$record::class);
        }

        // Ordenes y pagos nunca se borran, ni siquiera SUPER_ADMIN.
        $this->assertFalse($admin->can('delete', $order));
        $this->assertFalse($admin->can('delete', $payment));
    }

    public function test_plan_with_enrollments_is_still_not_deletable(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');
        $plan = $this->makePlan();
        $this->assertTrue($admin->can('delete', $plan));

        $this->makeEnrollment(Participant::factory()->create(), $plan);

        $this->assertFalse($admin->can('delete', $plan->fresh()));
    }

    public function test_refresh_token_of_a_deleted_representative_returns_401(): void
    {
        $user = User::factory()->create(['email' => 'borrar@example.com', 'password' => 'password123']);
        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email, 'password' => 'password123', 'remember' => false,
        ])->assertOk();

        app(DeleteRepresentative::class)->handle($user);

        $this->postJson('/api/auth/refresh', ['refreshToken' => $login->json('refreshToken')])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Sesion expirada']);
    }

    public function test_orphan_refresh_token_without_user_returns_401_instead_of_500(): void
    {
        // Huerfano anterior a F-053 (user_uuid NULL por la vieja regla nullOnDelete).
        $plain = Str::random(64);
        RefreshToken::create(['user_uuid' => null, 'token_hash' => hash('sha256', $plain), 'expires_at' => now()->addDay()]);

        $this->postJson('/api/auth/refresh', ['refreshToken' => $plain])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Sesion expirada']);
    }
}
