<?php

namespace Tests\Feature\Admin;

use App\Actions\Admin\DeleteRepresentative;
use App\Actions\Inscriptions\RecalculateInscriptionOrders;
use App\Actions\Onboarding\CompleteOnboarding;
use App\Filament\Resources\Enrollments\Pages\ListEnrollments;
use App\Filament\Resources\Enrollments\Pages\ViewEnrollment;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Widgets\PaymentsWidget;
use App\Filament\Resources\Participants\Pages\ListParticipants;
use App\Filament\Resources\Participants\Pages\ViewParticipant;
use App\Filament\Resources\Participants\RelationManagers\CorrectionRequestsRelationManager;
use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Filament\Resources\Plans\Pages\ListPlans;
use App\Filament\Resources\Plans\RelationManagers\AnnouncementsRelationManager;
use App\Filament\Resources\Plans\RelationManagers\PlanDaysRelationManager;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\EnrollmentsRelationManager;
use App\Filament\Resources\Users\RelationManagers\OnboardingDraftsRelationManager;
use App\Filament\Resources\Users\RelationManagers\ParticipantsRelationManager;
use App\Models\AdminUser;
use App\Models\Enrollment;
use App\Models\OnboardingDraft;
use App\Models\Order;
use App\Models\Participant;
use App\Models\ParticipantCorrectionRequest;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\PlanAnnouncement;
use App\Models\PlanDay;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * F-054: botones "Eliminar" del backoffice (solo SUPER_ADMIN,
 * `records.delete`), que llaman a las Actions de F-053; ordenes y pagos sin
 * representante ("Representante eliminado" + snapshot + filtro); y C-5
 * (acciones masivas de Planes/Productos/Dias/Avisos).
 */
class AdminDeleteActionsUiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): AdminUser
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole($role);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function makePlan(): Plan
    {
        return Plan::create([
            'name' => 'Plan UI Borrado', 'venue' => 'Sede Test', 'season' => 'Temporada 2026',
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
            'plan_name' => 'Plan UI Borrado', 'session_uuid' => (string) Str::uuid(), 'session_name' => 'Vespertina',
            'status' => 'PENDIENTE_PAGO', 'plan_type' => 'INDIVIDUAL',
            'total_amount' => 150, 'starts_at' => '2026-12-07', 'ends_at' => '2026-12-11',
        ]);
    }

    private function makeOrder(?User $user, float $total = 300, float $paid = 0, string $status = 'PENDIENTE_PAGO'): Order
    {
        return Order::create([
            'user_uuid' => $user?->uuid, 'ordered_at' => now()->toDateString(), 'items' => [],
            'total' => $total, 'paid' => $paid, 'status' => $status, 'is_registration' => false,
        ]);
    }

    private function makePayment(?User $user, Order $order, float $amount = 100, string $status = 'PENDIENTE_VERIFICACION'): Payment
    {
        return Payment::create([
            'user_uuid' => $user?->uuid, 'order_uuid' => $order->uuid, 'paid_at' => now()->toDateString(),
            'amount' => $amount, 'currency' => 'USD', 'method_code' => 'met_zelle', 'method_name' => 'Zelle',
            'reference' => 'TX-'.Str::random(6), 'status' => $status, 'receipt_name' => 'r.jpg',
            'idempotency_hash' => hash('sha256', (string) Str::uuid()),
        ]);
    }

    /** @return array<string, mixed> */
    private function makeFamily(): array
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'name' => 'Maria', 'last_name' => 'Perez', 'email' => 'maria@example.com', 'identification' => 'V-12345678',
        ]);
        $ana = Participant::factory()->create(['user_uuid' => $user->uuid, 'name' => 'Ana']);
        $luis = Participant::factory()->create(['user_uuid' => $user->uuid, 'name' => 'Luis']);
        $anaEnrollment = $this->makeEnrollment($ana);
        $this->makeEnrollment($luis);
        $correction = ParticipantCorrectionRequest::create(['participant_uuid' => $ana->uuid, 'section' => 'salud', 'message' => 'Corregir']);
        $draft = OnboardingDraft::create(['user_uuid' => $user->uuid, 'status' => 'COMPLETADO']);
        $order = $this->makeOrder($user);
        $payment = $this->makePayment($user, $order);

        return compact('user', 'ana', 'luis', 'anaEnrollment', 'correction', 'draft', 'order', 'payment');
    }

    /** @return array<string, array{0: string}> */
    public static function nonSuperAdminRoles(): array
    {
        return [
            'OPERACIONES' => ['OPERACIONES'],
            'FINANZAS' => ['FINANZAS'],
            'SOPORTE' => ['SOPORTE'],
            'LECTURA' => ['LECTURA'],
        ];
    }

    // --- Visibilidad -------------------------------------------------------

    public function test_super_admin_sees_every_delete_button(): void
    {
        $this->actingAsRole('SUPER_ADMIN');
        $f = $this->makeFamily();

        Livewire::test(ListUsers::class)->assertTableActionVisible('delete', $f['user']);
        Livewire::test(ViewUser::class, ['record' => $f['user']->getKey()])->assertActionVisible('delete');
        Livewire::test(ListParticipants::class)->assertTableActionVisible('delete', $f['ana']);
        Livewire::test(ViewParticipant::class, ['record' => $f['ana']->getKey()])->assertActionVisible('delete');
        Livewire::test(ListEnrollments::class)->assertTableActionVisible('delete', $f['anaEnrollment']);
        Livewire::test(ViewEnrollment::class, ['record' => $f['anaEnrollment']->getKey()])->assertActionVisible('delete');

        $rm = ['ownerRecord' => $f['user'], 'pageClass' => ViewUser::class];
        Livewire::test(ParticipantsRelationManager::class, $rm)->assertTableActionVisible('delete', $f['ana']);
        Livewire::test(EnrollmentsRelationManager::class, $rm)->assertTableActionVisible('delete', $f['anaEnrollment']);
        Livewire::test(OnboardingDraftsRelationManager::class, $rm)->assertTableActionVisible('delete', $f['draft']);
        Livewire::test(CorrectionRequestsRelationManager::class, ['ownerRecord' => $f['ana'], 'pageClass' => ViewParticipant::class])
            ->assertTableActionVisible('delete', $f['correction']);
    }

    #[DataProvider('nonSuperAdminRoles')]
    public function test_other_roles_never_see_delete_buttons(string $role): void
    {
        $admin = $this->actingAsRole($role);
        $f = $this->makeFamily();
        $checked = 0;

        if ($admin->can('viewAny', User::class)) {
            Livewire::test(ListUsers::class)->assertTableActionHidden('delete', $f['user']);
            Livewire::test(ViewUser::class, ['record' => $f['user']->getKey()])->assertActionHidden('delete');
            $rm = ['ownerRecord' => $f['user'], 'pageClass' => ViewUser::class];
            Livewire::test(ParticipantsRelationManager::class, $rm)->assertTableActionHidden('delete', $f['ana']);
            Livewire::test(EnrollmentsRelationManager::class, $rm)->assertTableActionHidden('delete', $f['anaEnrollment']);
            Livewire::test(OnboardingDraftsRelationManager::class, $rm)->assertTableActionHidden('delete', $f['draft']);
            $checked++;
        }

        if ($admin->can('viewAny', Participant::class)) {
            Livewire::test(ListParticipants::class)->assertTableActionHidden('delete', $f['ana']);
            Livewire::test(ViewParticipant::class, ['record' => $f['ana']->getKey()])->assertActionHidden('delete');
            Livewire::test(CorrectionRequestsRelationManager::class, ['ownerRecord' => $f['ana'], 'pageClass' => ViewParticipant::class])
                ->assertTableActionHidden('delete', $f['correction']);
            $checked++;
        }

        if ($admin->can('viewAny', Enrollment::class)) {
            Livewire::test(ListEnrollments::class)->assertTableActionHidden('delete', $f['anaEnrollment']);
            Livewire::test(ViewEnrollment::class, ['record' => $f['anaEnrollment']->getKey()])->assertActionHidden('delete');
            $checked++;
        }

        // Aunque no vea la pantalla, la policy tampoco lo deja borrar.
        foreach ([$f['user'], $f['ana'], $f['anaEnrollment'], $f['draft'], $f['correction']] as $record) {
            $this->assertFalse($admin->can('delete', $record), "{$role} no deberia poder borrar ".$record::class);
        }

        $this->assertNotNull($f['user']->fresh());
        $this->assertTrue($role === 'FINANZAS' || $checked > 0);
    }

    public function test_orders_and_payments_never_have_a_delete_button(): void
    {
        $this->actingAsRole('SUPER_ADMIN');
        $f = $this->makeFamily();

        Livewire::test(ListOrders::class)->assertTableActionDoesNotExist('delete');
        Livewire::test(ViewOrder::class, ['record' => $f['order']->getKey()])->assertActionDoesNotExist('delete');
        Livewire::test(PaymentsWidget::class)->assertTableActionDoesNotExist('delete');
    }

    // --- Representante -----------------------------------------------------

    public function test_representative_modal_shows_the_impact_and_requires_the_email(): void
    {
        $this->actingAsRole('SUPER_ADMIN');
        $f = $this->makeFamily();

        Livewire::test(ViewUser::class, ['record' => $f['user']->getKey()])
            ->mountAction('delete')
            ->assertMountedActionModalSee('2 participante(s)')
            ->assertMountedActionModalSee('2 inscripción(es)')
            ->assertMountedActionModalSee('1 orden(es) y 1 pago(s)')
            ->assertMountedActionModalSee('1 pago(s) en revisión')
            ->assertMountedActionModalSee('1 orden(es) con saldo')
            ->assertMountedActionModalSee('maria@example.com')
            ->setActionData(['confirmation' => 'otra@example.com'])
            ->callMountedAction()
            ->assertHasActionErrors(['confirmation']);

        $this->assertNotNull($f['user']->fresh());
        $this->assertSame(2, Participant::where('user_uuid', $f['user']->uuid)->count());
    }

    public function test_deleting_a_representative_from_the_view_page_cascades_and_keeps_orders_and_payments(): void
    {
        $this->actingAsRole('SUPER_ADMIN');
        $f = $this->makeFamily();

        Livewire::test(ViewUser::class, ['record' => $f['user']->getKey()])
            ->callAction('delete', data: ['confirmation' => 'MARIA@example.com '])
            ->assertHasNoActionErrors()
            ->assertNotified('Representante eliminado')
            ->assertRedirect(route('filament.admin.resources.users.index'));

        $this->assertNull($f['user']->fresh());
        $this->assertSame(0, Participant::count());
        $this->assertSame(0, Enrollment::count());
        $this->assertSame(0, ParticipantCorrectionRequest::count());
        $this->assertSame(0, OnboardingDraft::count());

        $order = $f['order']->fresh();
        $payment = $f['payment']->fresh();
        $this->assertNull($order->user_uuid);
        $this->assertNull($payment->user_uuid);
        $this->assertSame($order->uuid, $payment->order_uuid);
        $this->assertSame('maria@example.com', $order->representative_snapshot['email']);
        $this->assertSame('PENDIENTE_VERIFICACION', $payment->status);
    }

    public function test_deleting_a_representative_from_the_table_uses_the_action(): void
    {
        $this->actingAsRole('SUPER_ADMIN');
        $f = $this->makeFamily();
        $other = User::factory()->create();

        Livewire::test(ListUsers::class)
            ->callTableAction('delete', $f['user'], data: ['confirmation' => 'maria@example.com'])
            ->assertHasNoTableActionErrors();

        $this->assertNull($f['user']->fresh());
        $this->assertNotNull($other->fresh());
        $this->assertSame('Perez', $f['order']->fresh()->representative_snapshot['lastName']);
    }

    // --- Participante / inscripcion / borrador / correccion ----------------

    public function test_deleting_a_participant_from_the_view_page(): void
    {
        $this->actingAsRole('SUPER_ADMIN');
        $f = $this->makeFamily();

        Livewire::test(ViewParticipant::class, ['record' => $f['ana']->getKey()])
            ->mountAction('delete')
            ->assertMountedActionModalSee('no cambia de plazas')
            ->assertMountedActionModalSee('el total de la orden de inscripción sube')
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertRedirect(route('filament.admin.resources.participants.index'));

        $this->assertNull($f['ana']->fresh());
        $this->assertNull($f['anaEnrollment']->fresh());
        $this->assertNull($f['correction']->fresh());
        $this->assertNotNull($f['luis']->fresh());
        $this->assertNotNull($f['user']->fresh());
        $this->assertNotNull($f['order']->fresh());
    }

    public function test_deleting_records_from_the_representative_relation_managers(): void
    {
        $this->actingAsRole('SUPER_ADMIN');
        $f = $this->makeFamily();
        $rm = ['ownerRecord' => $f['user'], 'pageClass' => ViewUser::class];

        Livewire::test(ParticipantsRelationManager::class, $rm)
            ->callTableAction('delete', $f['ana'])
            ->assertHasNoTableActionErrors();
        $this->assertNull($f['ana']->fresh());

        $luisEnrollment = Enrollment::where('participant_uuid', $f['luis']->uuid)->firstOrFail();
        Livewire::test(EnrollmentsRelationManager::class, $rm)
            ->callTableAction('delete', $luisEnrollment)
            ->assertHasNoTableActionErrors();
        $this->assertNull($luisEnrollment->fresh());
        $this->assertNotNull($f['luis']->fresh());

        Livewire::test(OnboardingDraftsRelationManager::class, $rm)
            ->callTableAction('delete', $f['draft'])
            ->assertHasNoTableActionErrors();
        $this->assertNull($f['draft']->fresh());

        $this->assertNotNull($f['user']->fresh());
    }

    public function test_deleting_an_enrollment_keeps_participant_and_order(): void
    {
        $this->actingAsRole('SUPER_ADMIN');
        $f = $this->makeFamily();
        $orderBefore = $f['order']->fresh()->only(['total', 'paid', 'status']);

        Livewire::test(ViewEnrollment::class, ['record' => $f['anaEnrollment']->getKey()])
            ->mountAction('delete')
            ->assertMountedActionModalSee('no cambia')
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertRedirect(route('filament.admin.resources.enrollments.index'));

        $this->assertNull($f['anaEnrollment']->fresh());
        $this->assertNotNull($f['ana']->fresh());
        $this->assertSame($orderBefore, $f['order']->fresh()->only(['total', 'paid', 'status']));

        $luisEnrollment = Enrollment::where('participant_uuid', $f['luis']->uuid)->firstOrFail();
        Livewire::test(ListEnrollments::class)
            ->callTableAction('delete', $luisEnrollment)
            ->assertHasNoTableActionErrors();
        $this->assertNull($luisEnrollment->fresh());
    }

    public function test_deleting_a_correction_request_from_its_relation_manager(): void
    {
        $this->actingAsRole('SUPER_ADMIN');
        $f = $this->makeFamily();

        Livewire::test(CorrectionRequestsRelationManager::class, ['ownerRecord' => $f['ana'], 'pageClass' => ViewParticipant::class])
            ->callTableAction('delete', $f['correction'])
            ->assertHasNoTableActionErrors();

        $this->assertNull($f['correction']->fresh());
        $this->assertNotNull($f['ana']->fresh());
    }

    /**
     * Regresion del bug de RecalculateInscriptionOrders encontrado en F-054:
     * el saldo estaba invertido y una orden sin pagos quedaba PAGADA al
     * re-preciarse (p. ej. al borrar un hermano).
     */
    public function test_deleting_a_sibling_raises_the_order_total_and_keeps_it_pending(): void
    {
        $this->actingAsRole('SUPER_ADMIN');
        Storage::fake('public');
        Storage::fake('local');
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
                'pago' => ['pago' => ['modalidad' => 'completo', 'metodo' => 'Zelle', 'referencia' => 'REF-UI']],
                'adicionales' => [],
                'confirmacion' => ['aceptado' => true],
            ],
            'status' => 'COMPLETADO',
        ]);
        app(CompleteOnboarding::class)->handle($draft, UploadedFile::fake()->image('comprobante.jpg'));

        $order = $user->orders()->where('is_registration', true)->firstOrFail();
        $this->assertSame(260.0, (float) $order->total);
        $this->assertSame('PENDIENTE_PAGO', $order->status);

        $ana = $user->participants()->where('name', 'Ana')->firstOrFail();
        Livewire::test(ViewParticipant::class, ['record' => $ana->getKey()])
            ->callAction('delete')
            ->assertHasNoActionErrors();

        $order->refresh();
        $this->assertSame(300.0, (float) $order->total);
        $this->assertSame('PENDIENTE_PAGO', $order->status);
    }

    // --- Ordenes y pagos sin representante ----------------------------------

    public function test_orders_and_payments_without_representative_show_the_placeholder_and_snapshot(): void
    {
        $this->actingAsRole('SUPER_ADMIN');
        $f = $this->makeFamily();
        $keptUser = User::factory()->create(['name' => 'Carla', 'last_name' => 'Rojas']);
        $keptOrder = $this->makeOrder($keptUser);
        $keptPayment = $this->makePayment($keptUser, $keptOrder);
        $legacyOrphan = $this->makeOrder(null); // huerfano previo, sin snapshot

        app(DeleteRepresentative::class)->handle($f['user']);
        $orphanOrder = $f['order']->fresh();
        $orphanPayment = $f['payment']->fresh();

        Livewire::test(ListOrders::class)
            ->assertTableColumnStateSet('user.name', 'Representante eliminado: Maria Perez', $orphanOrder)
            ->assertTableColumnStateSet('user.name', 'Representante eliminado', $legacyOrphan)
            ->assertTableColumnStateSet('user.name', 'Carla Rojas', $keptOrder)
            ->assertSee('maria@example.com · ID V-12345678')
            ->filterTable('without_representative')
            ->assertCanSeeTableRecords([$orphanOrder, $legacyOrphan])
            ->assertCanNotSeeTableRecords([$keptOrder]);

        Livewire::test(ListOrders::class)
            ->searchTable('Maria')
            ->assertCanSeeTableRecords([$orphanOrder])
            ->assertCanNotSeeTableRecords([$keptOrder]);

        Livewire::test(PaymentsWidget::class)
            ->assertTableColumnStateSet('user.name', 'Representante eliminado: Maria Perez', $orphanPayment)
            ->assertTableColumnStateSet('user.name', 'Carla Rojas', $keptPayment)
            ->filterTable('without_representative')
            ->assertCanSeeTableRecords([$orphanPayment])
            ->assertCanNotSeeTableRecords([$keptPayment]);

        Livewire::test(ViewOrder::class, ['record' => $orphanOrder->getKey()])
            ->assertSee('Representante eliminado: Maria Perez (maria@example.com · ID V-12345678)');
    }

    public function test_approve_reject_and_cancel_remain_available_without_representative(): void
    {
        $this->actingAsRole('SUPER_ADMIN');
        $f = $this->makeFamily();
        $second = $this->makePayment($f['user'], $f['order'], 50);
        app(DeleteRepresentative::class)->handle($f['user']);

        Livewire::test(PaymentsWidget::class)
            ->assertTableActionVisible('approve', $f['payment']->fresh())
            ->callTableAction('approve', $f['payment']->fresh())
            ->assertHasNoTableActionErrors()
            ->callTableAction('reject', $second->fresh(), data: ['reason' => 'Duplicado'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('APROBADO', $f['payment']->fresh()->status);
        $this->assertSame('RECHAZADO', $second->fresh()->status);

        Livewire::test(ViewOrder::class, ['record' => $f['order']->getKey()])
            ->assertActionVisible('cancel')
            ->callAction('cancel', data: ['reason' => 'Representante eliminado'])
            ->assertHasNoActionErrors();

        $this->assertSame('CANCELADA', $f['order']->fresh()->status);
    }

    // --- C-5: acciones masivas ---------------------------------------------

    public function test_plans_bulk_delete_respects_the_per_record_policy(): void
    {
        $this->actingAsRole('OPERACIONES'); // plans.manage
        $withEnrollments = $this->makePlan();
        $empty = $this->makePlan();
        $participant = Participant::factory()->create(['user_uuid' => User::factory()->create()->uuid]);
        $this->makeEnrollment($participant, $withEnrollments);

        Livewire::test(ListPlans::class)
            ->assertTableBulkActionVisible('delete')
            ->callTableBulkAction('delete', [$withEnrollments, $empty]);

        $this->assertNotNull($withEnrollments->fresh());
        $this->assertNull($empty->fresh());
    }

    public function test_plans_bulk_delete_is_hidden_without_plans_manage(): void
    {
        $this->actingAsRole('LECTURA');
        $this->makePlan();

        Livewire::test(ListPlans::class)->assertTableBulkActionHidden('delete');
    }

    public function test_products_bulk_delete_works_for_products_manage(): void
    {
        $this->actingAsRole('FINANZAS'); // products.manage
        $products = collect([1, 2])->map(fn (int $i): Product => Product::create([
            'name' => "Producto {$i}", 'category' => 'Ropa', 'price' => 10, 'in_stock' => true,
        ]));

        Livewire::test(ListProducts::class)
            ->assertTableBulkActionVisible('delete')
            ->callTableBulkAction('delete', $products->all());

        $this->assertSame(0, Product::count());
    }

    public function test_plan_days_and_announcements_bulk_delete_work_on_the_plan_edit_page(): void
    {
        $this->actingAsRole('OPERACIONES');
        $plan = $this->makePlan();
        $day = PlanDay::create(['plan_uuid' => $plan->uuid, 'day_number' => 1, 'date' => '2026-12-07', 'title' => 'Dia 1', 'status' => 'PLANIFICADO']);
        $announcement = PlanAnnouncement::create(['plan_uuid' => $plan->uuid, 'title' => 'Aviso', 'body' => 'Texto', 'severity' => 'INFO']);
        $rm = ['ownerRecord' => $plan, 'pageClass' => EditPlan::class];

        Livewire::test(PlanDaysRelationManager::class, $rm)
            ->assertTableBulkActionVisible('delete')
            ->callTableBulkAction('delete', [$day]);
        Livewire::test(AnnouncementsRelationManager::class, $rm)
            ->assertTableBulkActionVisible('delete')
            ->callTableBulkAction('delete', [$announcement]);

        $this->assertNull($day->fresh());
        $this->assertNull($announcement->fresh());
        $this->assertNotNull($plan->fresh());
    }

    // --- Boton "Eliminar" por fila (doble confirmacion) ---------------------

    public function test_plan_row_delete_removes_an_empty_plan_and_is_disabled_with_enrollments(): void
    {
        $this->actingAsRole('OPERACIONES'); // plans.manage
        $withEnrollments = $this->makePlan();
        $empty = $this->makePlan();
        $participant = Participant::factory()->create(['user_uuid' => User::factory()->create()->uuid]);
        $this->makeEnrollment($participant, $withEnrollments);

        Livewire::test(ListPlans::class)
            ->assertTableActionVisible('delete', $withEnrollments)
            ->assertTableActionDisabled('delete', $withEnrollments)
            ->assertTableActionEnabled('delete', $empty)
            ->callTableAction('delete', $empty)
            ->assertHasNoTableActionErrors();

        Livewire::test(EditPlan::class, ['record' => $withEnrollments->getKey()])
            ->assertActionDisabled('delete');

        $this->assertNotNull($withEnrollments->fresh());
        $this->assertNull($empty->fresh());
    }

    public function test_plan_row_delete_is_hidden_without_plans_manage(): void
    {
        $this->actingAsRole('LECTURA');
        $plan = $this->makePlan();

        Livewire::test(ListPlans::class)->assertTableActionHidden('delete', $plan);
    }

    public function test_product_and_payment_method_row_delete(): void
    {
        $this->actingAsRole('SUPER_ADMIN');
        $product = Product::create(['name' => 'Gorra', 'category' => 'Ropa', 'price' => 10, 'in_stock' => true]);
        $method = PaymentMethod::create(['code' => 'TEST', 'type' => 'DIRECTO', 'name' => 'Test', 'description' => 'Metodo de prueba', 'data' => [], 'active' => true]);

        Livewire::test(ListProducts::class)
            ->assertTableActionVisible('delete', $product)
            ->callTableAction('delete', $product)
            ->assertHasNoTableActionErrors();
        Livewire::test(ListPaymentMethods::class)
            ->assertTableActionVisible('delete', $method)
            ->callTableAction('delete', $method)
            ->assertHasNoTableActionErrors();

        $this->assertNull($product->fresh());
        $this->assertNull($method->fresh());
    }

    public function test_product_and_payment_method_row_delete_hidden_for_lectura(): void
    {
        $admin = $this->actingAsRole('LECTURA');
        $product = Product::create(['name' => 'Gorra', 'category' => 'Ropa', 'price' => 10, 'in_stock' => true]);
        $method = PaymentMethod::create(['code' => 'TEST', 'type' => 'DIRECTO', 'name' => 'Test', 'description' => 'Metodo de prueba', 'data' => [], 'active' => true]);

        // LECTURA no entra a Productos ni a Metodos de pago; la policy (que
        // decide la visibilidad del boton) tampoco le deja borrar.
        $this->assertFalse($admin->can('delete', $product));
        $this->assertFalse($admin->can('delete', $method));
    }

    public function test_plan_day_and_announcement_row_delete(): void
    {
        $this->actingAsRole('OPERACIONES');
        $plan = $this->makePlan();
        $day = PlanDay::create(['plan_uuid' => $plan->uuid, 'day_number' => 1, 'date' => '2026-12-07', 'title' => 'Dia 1', 'status' => 'PLANIFICADO']);
        $announcement = PlanAnnouncement::create(['plan_uuid' => $plan->uuid, 'title' => 'Aviso', 'body' => 'Texto', 'severity' => 'INFO']);
        $rm = ['ownerRecord' => $plan, 'pageClass' => EditPlan::class];

        Livewire::test(PlanDaysRelationManager::class, $rm)
            ->callTableAction('delete', $day)
            ->assertHasNoTableActionErrors();
        Livewire::test(AnnouncementsRelationManager::class, $rm)
            ->callTableAction('delete', $announcement)
            ->assertHasNoTableActionErrors();

        $this->assertNull($day->fresh());
        $this->assertNull($announcement->fresh());
    }

    // --- Verifier F-054: endurecimiento -------------------------------------

    /**
     * Un rol sin records.delete no puede forzar el borrado llamando a mano a
     * los metodos Livewire (mountAction/callMountedAction), ni en la ficha ni
     * en la fila de la tabla.
     */
    public function test_non_super_admin_cannot_force_delete_through_raw_livewire_calls(): void
    {
        $this->actingAsRole('SOPORTE'); // users.view + participants.view
        $f = $this->makeFamily();

        Livewire::test(ViewUser::class, ['record' => $f['user']->getKey()])
            ->call('mountAction', 'delete')
            ->set('mountedActions.0.data.confirmation', 'maria@example.com')
            ->call('callMountedAction');

        Livewire::test(ListUsers::class)
            ->call('mountAction', 'delete', [], ['table' => true, 'recordKey' => $f['user']->getKey()])
            ->set('mountedActions.0.data.confirmation', 'maria@example.com')
            ->call('callMountedAction');

        Livewire::test(ViewParticipant::class, ['record' => $f['ana']->getKey()])
            ->call('mountAction', 'delete')
            ->call('callMountedAction');

        Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $f['user'], 'pageClass' => ViewUser::class])
            ->call('mountAction', 'delete', [], ['table' => true, 'recordKey' => $f['ana']->getKey()])
            ->call('callMountedAction');

        $this->assertNotNull($f['user']->fresh());
        $this->assertNotNull($f['ana']->fresh());
        $this->assertNotNull($f['anaEnrollment']->fresh());
        $this->assertNull($f['order']->fresh()->representative_snapshot);
    }

    /**
     * Regresion directa del bug de RecalculateInscriptionOrders (saldo
     * invertido): una orden sin pagos sigue PENDIENTE_PAGO, una CANCELADA no
     * revive y una con credito a favor (abonado > total) queda PAGADA sin
     * tocar lo abonado.
     */
    public function test_recalculate_inscription_orders_keeps_status_consistent_with_the_balance(): void
    {
        $user = User::factory()->create();
        $this->makePlan(); // PUBLICADO: operativo, descuento 20 desde 2 participantes
        Participant::factory()->create(['user_uuid' => $user->uuid]); // familia de 1: sin descuento

        $base = [['nombre' => 'Inscripción', 'variante' => 'Vespertina', 'qty' => 1, 'precio' => 150]];
        $withDiscount = [...$base, ['nombre' => 'Descuento hermanos', 'variante' => 'Vespertina', 'qty' => 1, 'precio' => -20]];
        $make = fn (array $items, float $total, float $paid, string $status): Order => Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(), 'items' => $items,
            'total' => $total, 'paid' => $paid, 'status' => $status, 'is_registration' => true,
        ]);

        $unpaid = $make($base, 150, 0, 'PENDIENTE_PAGO');
        $cancelled = $make($withDiscount, 130, 0, 'CANCELADA');
        $credit = $make($withDiscount, 130, 200, 'PAGADA');
        $paidExact = $make($withDiscount, 130, 130, 'PAGADA');

        app(RecalculateInscriptionOrders::class)->handle($user);

        $this->assertSame('PENDIENTE_PAGO', $unpaid->fresh()->status);
        $this->assertSame(150.0, (float) $unpaid->fresh()->total);

        // F-059 (api-A-5): una CANCELADA ya no se re-precia (antes su total
        // pasaba a 150); conserva estado, total e items.
        $this->assertSame('CANCELADA', $cancelled->fresh()->status);
        $this->assertSame(130.0, (float) $cancelled->fresh()->total);
        $this->assertSame($withDiscount, $cancelled->fresh()->items);

        $this->assertSame('PAGADA', $credit->fresh()->status);
        $this->assertSame(150.0, (float) $credit->fresh()->total);
        $this->assertSame(200.0, (float) $credit->fresh()->paid);

        // Se quita el descuento: el total sube por encima de lo abonado.
        $this->assertSame('PENDIENTE_PAGO', $paidExact->fresh()->status);
        $this->assertSame(130.0, (float) $paidExact->fresh()->paid);
    }
}
