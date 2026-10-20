<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\PaymentsRelationManager as OrderPaymentsRelationManager;
use App\Filament\Resources\Participants\Pages\ViewParticipant;
use App\Filament\Resources\Participants\RelationManagers\CorrectionRequestsRelationManager;
use App\Models\AdminUser;
use App\Models\Order;
use App\Models\Participant;
use App\Models\ParticipantCorrectionRequest;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * F-062: permisos y auditoria del backoffice (review 2026-10-19: api-A-6,
 * api-A-9, api-A-14) + compatibilidad: cada rol conserva lo que usaba
 * legitimamente (progress/compat-rules-2026-10-19.md).
 */
class BackofficePermissionsAndAuditTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithRole(string $role): AdminUser
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole($role);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    /** @return array{0: Participant, 1: ParticipantCorrectionRequest, 2: AdminUser} */
    private function pendingCorrection(): array
    {
        $requester = AdminUser::factory()->create();
        $participant = Participant::factory()->for(User::factory()->create(), 'user')->create();
        $request = ParticipantCorrectionRequest::create([
            'participant_uuid' => $participant->uuid, 'section' => 'salud', 'message' => 'Falta info',
            'status' => 'PENDIENTE', 'requested_by' => $requester->uuid,
        ]);

        return [$participant, $request, $requester];
    }

    private function correctionsRm(Participant $participant): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(CorrectionRequestsRelationManager::class, [
            'ownerRecord' => $participant,
            'pageClass' => ViewParticipant::class,
        ]);
    }

    /** @return array{0: Order, 1: Payment} */
    private function paymentWithReceipt(bool $storeFile = true): array
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [], 'total' => 20, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);
        if ($storeFile) {
            Storage::disk('local')->put('comprobantes/r.jpg', 'contenido-del-comprobante');
        }
        $payment = Payment::create([
            'user_uuid' => $user->uuid, 'order_uuid' => $order->uuid, 'paid_at' => now()->toDateString(),
            'amount' => 20, 'currency' => 'USD', 'method_code' => 'met_zelle', 'method_name' => 'Zelle',
            'reference' => 'TX-F062', 'status' => 'PENDIENTE_VERIFICACION',
            'receipt_path' => 'comprobantes/r.jpg', 'receipt_name' => 'r.jpg',
            'idempotency_hash' => hash('sha256', uniqid('', true)),
        ]);

        return [$order, $payment];
    }

    private function paymentsRm(Order $order): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(OrderPaymentsRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => ViewOrder::class,
        ]);
    }

    /**
     * Simula un cliente que llama la accion por Livewire saltandose el boton
     * oculto: `mountAction` directo y, ademas, el estado `mountedActions`
     * manipulado a mano antes de `callMountedAction`.
     */
    private function forceLivewireCall(\Livewire\Features\SupportTesting\Testable $component, string $name, ?\Illuminate\Database\Eloquent\Model $record, array $data = []): void
    {
        $context = ['table' => true];
        if ($record) {
            $context['recordKey'] = (string) $record->getKey();
        }

        $component->call('mountAction', $name, [], $context);
        $component->set('mountedActions', [['name' => $name, 'arguments' => [], 'context' => $context, 'data' => $data]]);
        $component->call('callMountedAction');
    }

    // ── api-A-6: "Resolver" solicitud de correccion ──

    public function test_lectura_does_not_see_resolve_and_cannot_resolve_via_livewire(): void
    {
        $this->adminWithRole('LECTURA');
        [$participant, $request] = $this->pendingCorrection();

        $this->correctionsRm($participant)
            ->assertTableActionHidden('resolve', $request)
            ->tap(fn ($c) => $this->forceLivewireCall($c, 'resolve', $request, ['status' => 'RESUELTA', 'resolution' => 'Hack']));

        $fresh = $request->fresh();
        $this->assertSame('PENDIENTE', $fresh->status);
        $this->assertNull($fresh->resolved_by);
        $this->assertNull($fresh->resolution);
    }

    public function test_lectura_cannot_create_a_correction_request(): void
    {
        $this->adminWithRole('LECTURA');
        [$participant] = $this->pendingCorrection();

        $this->correctionsRm($participant)
            ->assertTableActionHidden('create')
            ->tap(fn ($c) => $this->forceLivewireCall($c, 'create', null, ['section' => 'salud', 'message' => 'x']));

        $this->assertSame(1, $participant->correctionRequests()->count());
    }

    /** @return array<string, array{0: string}> */
    public static function correctionManagersProvider(): array
    {
        return [
            'SUPER_ADMIN' => ['SUPER_ADMIN'],
            'OPERACIONES (participants.edit)' => ['OPERACIONES'],
            'SOPORTE (corrections.manage, compat: ya resolvia)' => ['SOPORTE'],
        ];
    }

    #[DataProvider('correctionManagersProvider')]
    public function test_roles_that_manage_corrections_keep_resolving_them(string $role): void
    {
        $admin = $this->adminWithRole($role);
        [$participant, $request] = $this->pendingCorrection();

        $this->correctionsRm($participant)
            ->assertTableActionVisible('resolve', $request)
            ->callTableAction('resolve', $request, data: ['status' => 'RESUELTA', 'resolution' => 'Listo'])
            ->assertHasNoTableActionErrors();

        $fresh = $request->fresh();
        $this->assertSame('RESUELTA', $fresh->status);
        $this->assertSame($admin->uuid, $fresh->resolved_by);
    }

    #[DataProvider('correctionManagersProvider')]
    public function test_roles_that_manage_corrections_can_request_them(string $role): void
    {
        $admin = $this->adminWithRole($role);
        [$participant] = $this->pendingCorrection();

        $this->correctionsRm($participant)
            ->callTableAction('create', data: ['section' => 'salud', 'message' => 'Revisar alergias'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, $participant->correctionRequests()->count());
        $this->assertSame($admin->uuid, $participant->correctionRequests()->where('message', 'Revisar alergias')->sole()->requested_by);
    }

    // ── api-A-9: descargar comprobantes ──

    public function test_lectura_does_not_see_receipt_and_cannot_download_it_via_livewire(): void
    {
        $this->adminWithRole('LECTURA');
        [$order, $payment] = $this->paymentWithReceipt();

        $this->paymentsRm($order)
            ->assertTableActionHidden('receipt', $payment)
            ->tap(fn ($c) => $this->forceLivewireCall($c, 'receipt', $payment))
            ->assertNoFileDownloaded();
    }

    /** @return array<string, array{0: string}> */
    public static function financialRolesProvider(): array
    {
        return [
            'SUPER_ADMIN (incluye admin@cappixtremo.com)' => ['SUPER_ADMIN'],
            'FINANZAS' => ['FINANZAS'],
        ];
    }

    #[DataProvider('financialRolesProvider')]
    public function test_financial_roles_keep_downloading_receipts(string $role): void
    {
        $this->adminWithRole($role);
        [$order, $payment] = $this->paymentWithReceipt();

        $this->paymentsRm($order)
            ->assertTableActionVisible('receipt', $payment)
            ->callTableAction('receipt', $payment)
            ->assertFileDownloaded('r.jpg');
    }

    public function test_missing_receipt_file_in_the_panel_shows_a_notice_instead_of_an_empty_file(): void
    {
        $this->adminWithRole('FINANZAS');
        [$order, $payment] = $this->paymentWithReceipt(storeFile: false);

        $this->paymentsRm($order)
            ->callTableAction('receipt', $payment)
            ->assertNoFileDownloaded()
            ->assertNotified('El archivo del comprobante no está disponible.');
    }

    // ── Compatibilidad: los roles conservan lo que usaban ──

    public function test_finanzas_keeps_approving_and_rejecting_payments(): void
    {
        $this->adminWithRole('FINANZAS');
        [$order, $payment] = $this->paymentWithReceipt();

        $this->paymentsRm($order)
            ->assertTableActionVisible('approve', $payment)
            ->assertTableActionVisible('reject', $payment)
            ->callTableAction('approve', $payment)
            ->assertHasNoTableActionErrors();

        $this->assertSame('APROBADO', $payment->fresh()->status);
    }

    public function test_lectura_still_sees_the_payments_table_and_corrections(): void
    {
        $this->adminWithRole('LECTURA');
        [$order, $payment] = $this->paymentWithReceipt();
        [$participant, $request] = $this->pendingCorrection();

        $this->paymentsRm($order)->assertCanSeeTableRecords([$payment]);
        $this->correctionsRm($participant)->assertCanSeeTableRecords([$request]);
    }

    // ── api-A-14: auditoria de total ──

    public function test_order_total_and_items_changes_are_audited(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [['nombre' => 'Inscripción · Plan', 'variante' => 'S', 'qty' => 1, 'precio' => 150]],
            'total' => 150, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => true,
        ]);

        $order->update([
            'items' => [['nombre' => 'Inscripción · Plan', 'variante' => 'S', 'qty' => 2, 'precio' => 150]],
            'total' => 300,
        ]);

        $activity = Activity::where('log_name', 'order')->where('subject_id', $order->id)->where('event', 'updated')->latest('id')->firstOrFail();
        $this->assertEquals(300, $activity->properties['attributes']['total']);
        $this->assertEquals(150, $activity->properties['old']['total']);
        $this->assertSame(2, $activity->properties['attributes']['items'][0]['qty']);
        $this->assertArrayNotHasKey('status', $activity->properties['attributes']);
    }

    public function test_existing_activity_log_rows_are_not_rewritten(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [], 'total' => 20, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);
        $before = Activity::where('log_name', 'order')->count();

        $order->update(['paid' => 10]);

        // Solo se agrega la fila del cambio nuevo; lo previo queda igual.
        $this->assertSame($before + 1, Activity::where('log_name', 'order')->count());
    }
}
