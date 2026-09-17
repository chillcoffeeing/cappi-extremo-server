<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_report_payment_with_reference_and_receipt(): void
    {
        Storage::fake('public');
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

    public function test_duplicate_payment_reference_is_rejected(): void
    {
        Storage::fake('public');
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
}
