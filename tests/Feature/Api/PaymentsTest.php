<?php

namespace Tests\Feature\Api;

use App\Actions\Payments\ApprovePayment;
use App\Actions\Payments\RejectPayment;
use App\Models\AdminUser;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaymentMethodSeeder::class);
    }

    public function test_user_can_report_payment_with_reference_and_receipt(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/pagos', [
            'metodoId' => 'met_zelle',
            'monto' => 20,
            'fecha' => now()->format('Y-m-d'),
            'referencia' => 'TX-001',
            'comprobante' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.estado', 'PENDIENTE_VERIFICACION')
            ->assertJsonPath('data.referencia', 'TX-001')
            ->assertJsonPath('data.comprobanteNombre', 'receipt.jpg');
    }

    public function test_owner_can_download_their_own_receipt_but_a_stranger_cannot(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        Sanctum::actingAs($owner);

        $response = $this->post('/api/pagos', [
            'metodoId' => 'met_zelle',
            'monto' => 20,
            'fecha' => now()->format('Y-m-d'),
            'referencia' => 'TX-002',
            'comprobante' => UploadedFile::fake()->image('receipt.jpg'),
        ]);
        $paymentUuid = $response->json('data.id');

        $this->get("/api/pagos/{$paymentUuid}/comprobante")->assertOk();

        Sanctum::actingAs($stranger);
        $this->get("/api/pagos/{$paymentUuid}/comprobante")->assertNotFound();
    }

    public function test_duplicate_payment_reference_is_rejected(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $payload = [
            'metodoId' => 'met_zelle',
            'monto' => 20,
            'fecha' => now()->format('Y-m-d'),
            'referencia' => 'TX-001',
            'comprobante' => UploadedFile::fake()->image('receipt.jpg'),
        ];

        $this->post('/api/pagos', $payload)->assertCreated();
        $this->post('/api/pagos', $payload)->assertStatus(422);
    }

    public function test_rejected_report_can_be_sent_again_but_pending_or_approved_still_block(): void
    {
        // F-052 (A-1/A-7) en POST /pagos: un RECHAZADO no bloquea el re-reporte
        // identico (sin violar el indice unico); pendiente y aprobado si.
        Storage::fake('local');
        $user = User::factory()->create();
        $admin = AdminUser::factory()->create();
        Sanctum::actingAs($user);
        $payload = fn (string $monto = '20') => [
            'metodoId' => 'met_zelle',
            'monto' => $monto,
            'fecha' => now()->format('Y-m-d'),
            'referencia' => 'TX-RE',
            'comprobante' => UploadedFile::fake()->image('receipt.jpg'),
        ];

        $this->post('/api/pagos', $payload())->assertCreated();
        $this->post('/api/pagos', $payload('20.00'), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Este pago ya fue reportado. Evita duplicados.']);

        app(RejectPayment::class)->handle(Payment::sole(), 'Comprobante ilegible', $admin);

        $this->post('/api/pagos', $payload())->assertCreated();
        $this->assertSame(2, Payment::count());

        app(ApprovePayment::class)->handle(Payment::where('status', 'PENDIENTE_VERIFICACION')->sole(), $admin);
        $this->post('/api/pagos', $payload(), ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame(2, Payment::count());
    }
}
