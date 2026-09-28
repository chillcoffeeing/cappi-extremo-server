<?php

namespace Tests\Feature\Api;

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
 * F-035: modulo de subida de archivos (comprobantes + fotos).
 *
 * - Formatos de imagen estandar (jpg, jpeg, png, webp, gif, bmp) y pdf para
 *   comprobantes; HEIC y no-imagenes se rechazan con 422 por campo.
 * - Fotos por POST multipart + `_method=PUT`: PHP no parsea multipart en PUT
 *   real, asi que el portal usa method spoofing.
 * - Comprobante obligatorio en `POST /onboarding/{id}/complete` para
 *   metodos DIRECTOS.
 */
class UploadsTest extends TestCase
{
    use RefreshDatabase;

    private const JSON = ['Accept' => 'application/json'];

    public function test_representative_photo_via_post_with_method_spoofing_accepts_webp(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/representante/foto', [
            '_method' => 'PUT',
            'foto' => UploadedFile::fake()->image('perfil.webp'),
        ], self::JSON)
            ->assertOk()
            ->assertJsonPath('data.perfil.fotoUrl', fn (string $url): bool => str_contains($url, '/storage/profiles/'));

        $this->assertNotEmpty(Storage::disk('public')->allFiles('profiles'));
    }

    public function test_participant_photo_via_post_with_method_spoofing_accepts_gif_and_bmp(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);
        Sanctum::actingAs($user);

        foreach (['carnet.gif', 'carnet.bmp', 'carnet.jpeg'] as $name) {
            $this->post("/api/participantes/{$participant->uuid}/foto", [
                '_method' => 'PUT',
                'foto' => UploadedFile::fake()->image($name),
            ], self::JSON)->assertOk();
        }

        $this->assertNotEmpty(Storage::disk('public')->allFiles('participants'));
    }

    public function test_photo_rejects_heic_and_non_images_with_spanish_field_error(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $heic = UploadedFile::fake()->create('IMG_0001.heic', 200, 'image/heic');
        $this->post('/api/representante/foto', ['_method' => 'PUT', 'foto' => $heic], self::JSON)
            ->assertStatus(422)
            ->assertJsonPath('errors.foto.0', 'La foto debe ser una imagen JPG, PNG, WEBP, GIF o BMP.');

        $pdf = UploadedFile::fake()->create('foto.pdf', 50, 'application/pdf');
        $this->post('/api/representante/foto', ['_method' => 'PUT', 'foto' => $pdf], self::JSON)
            ->assertStatus(422)
            ->assertJsonValidationErrors('foto');
    }

    public function test_photo_larger_than_5mb_is_rejected(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $big = UploadedFile::fake()->image('grande.jpg')->size(6000);
        $this->post('/api/representante/foto', ['_method' => 'PUT', 'foto' => $big], self::JSON)
            ->assertStatus(422)
            ->assertJsonPath('errors.foto.0', 'La foto no puede pesar más de 5 MB.');
    }

    public function test_order_payment_accepts_webp_receipt_without_reference(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = $this->makeOrder($user, 60);

        $this->post("/api/ordenes/{$order->uuid}/enlazar-pago", [
            'monto' => 20,
            'esCompleto' => false,
            'metodoId' => 'met_zelle',
            'metodoNombre' => 'Zelle',
            'referencia' => '',
            'comprobante' => UploadedFile::fake()->image('comprobante.webp'),
        ], self::JSON)->assertOk();

        // Un segundo abono identico sin referencia no choca con el hash unico.
        $this->post("/api/ordenes/{$order->uuid}/enlazar-pago", [
            'monto' => 20,
            'esCompleto' => false,
            'metodoId' => 'met_zelle',
            'metodoNombre' => 'Zelle',
            'comprobante' => UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'),
        ], self::JSON)->assertOk();

        $payments = $order->payments()->get();
        $this->assertCount(2, $payments);
        $this->assertNull($payments->first()->reference);
        Storage::disk('local')->assertExists($payments->first()->receipt_path);
    }

    public function test_order_payment_rejects_heic_and_text_receipts(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = $this->makeOrder($user, 60);

        foreach ([
            UploadedFile::fake()->create('comprobante.heic', 100, 'image/heic'),
            UploadedFile::fake()->create('comprobante.txt', 1, 'text/plain'),
        ] as $file) {
            $this->post("/api/ordenes/{$order->uuid}/enlazar-pago", [
                'monto' => 60,
                'esCompleto' => true,
                'metodoId' => 'met_zelle',
                'metodoNombre' => 'Zelle',
                'referencia' => 'TX-1',
                'comprobante' => $file,
            ], self::JSON)
                ->assertStatus(422)
                ->assertJsonPath('errors.comprobante.0', 'El comprobante debe ser PDF, JPG, PNG, WEBP, GIF o BMP.');
        }

        $this->assertSame(0, $order->payments()->count());
    }

    public function test_payment_report_accepts_bmp_receipt(): void
    {
        Storage::fake('local');
        PaymentMethod::create([
            'code' => 'met_zelle', 'type' => 'DIRECTO', 'name' => 'Zelle',
            'description' => 'Zelle', 'data' => ['instrucciones' => '', 'detalle' => []], 'active' => true,
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/pagos', [
            'metodoId' => 'met_zelle',
            'monto' => 40,
            'fecha' => now()->toDateString(),
            'referencia' => 'REF-BMP',
            'comprobante' => UploadedFile::fake()->image('comprobante.bmp'),
        ], self::JSON)->assertCreated();
    }

    public function test_onboarding_direct_method_requires_receipt(): void
    {
        Storage::fake('local');
        [$user, $draftId] = $this->prepareOnboarding('DIRECTO');

        $this->post("/api/onboarding/{$draftId}/complete", [], self::JSON)
            ->assertStatus(422)
            ->assertJsonValidationErrors('comprobante');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'onboarding_status' => 'INCOMPLETO']);
        $this->assertSame(0, Payment::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_onboarding_direct_method_rejects_invalid_receipt_format(): void
    {
        Storage::fake('local');
        [, $draftId] = $this->prepareOnboarding('DIRECTO');

        $this->post("/api/onboarding/{$draftId}/complete", [
            'comprobante' => UploadedFile::fake()->create('comprobante.heic', 100, 'image/heic'),
        ], self::JSON)
            ->assertStatus(422)
            ->assertJsonPath('errors.comprobante.0', 'El comprobante debe ser PDF, JPG, PNG, WEBP, GIF o BMP.');
    }

    public function test_onboarding_direct_method_stores_receipt_and_it_is_downloadable(): void
    {
        Storage::fake('local');
        [$user, $draftId] = $this->prepareOnboarding('DIRECTO');

        $this->post("/api/onboarding/{$draftId}/complete", [
            'comprobante' => UploadedFile::fake()->image('transferencia.webp'),
        ], self::JSON)
            ->assertOk()
            ->assertJsonPath('status', 'COMPLETADO');

        $payment = Payment::where('user_uuid', $user->uuid)->sole();
        $this->assertSame('transferencia.webp', $payment->receipt_name);
        $this->assertSame('REF-ONB', $payment->reference);
        Storage::disk('local')->assertExists($payment->receipt_path);

        $this->get("/api/pagos/{$payment->uuid}/comprobante")->assertOk();
        $this->getJson('/api/pagos')
            ->assertOk()
            ->assertJsonPath('data.0.comprobanteUrl', fn (string $url): bool => str_contains($url, '/comprobante'));
    }

    public function test_onboarding_coordinated_remote_method_does_not_require_receipt(): void
    {
        Storage::fake('local');
        [$user, $draftId] = $this->prepareOnboarding('COORDINADO_REMOTO');

        $this->post("/api/onboarding/{$draftId}/complete", [], self::JSON)
            ->assertOk()
            ->assertJsonPath('status', 'COMPLETADO');

        $payment = Payment::where('user_uuid', $user->uuid)->sole();
        $this->assertNull($payment->receipt_path);
    }

    private function makeOrder(User $user, float $total): Order
    {
        return Order::create([
            'user_uuid' => $user->uuid, 'ordered_at' => now()->toDateString(),
            'items' => [['nombre' => 'Gorra', 'variante' => 'Única', 'qty' => 1, 'precio' => $total]],
            'total' => $total, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false,
        ]);
    }

    /** @return array{0: User, 1: string} */
    private function prepareOnboarding(string $methodType): array
    {
        $plan = Plan::create([
            'name' => 'Plan de prueba', 'venue' => 'Sede', 'season' => 'Temporada',
            'starts_at' => '2026-12-07', 'ends_at' => '2026-12-11', 'capacity' => 20,
            'price' => 150, 'age_min' => 5, 'age_max' => 15, 'status' => 'PUBLICADO',
        ]);
        PaymentMethod::create([
            'code' => 'met_test', 'type' => $methodType, 'name' => 'Metodo test',
            'description' => 'Metodo de prueba',
            'data' => $methodType === 'COORDINADO_REMOTO'
                ? ['whatsapp' => ['mensajeOnboarding' => '', 'mensajeDashboard' => '', 'linkTexto' => '', 'link' => '']]
                : ['instrucciones' => '', 'detalle' => []],
            'active' => true,
        ]);
        $user = User::factory()->create(['onboarding_status' => 'INCOMPLETO']);
        $draftId = (string) Str::uuid();
        Sanctum::actingAs($user);

        $this->postJson("/api/onboarding/{$draftId}/step", [
            'stepId' => 'cuenta',
            'data' => ['planId' => $plan->uuid],
        ])->assertOk();
        $this->postJson("/api/onboarding/{$draftId}/step", [
            'stepId' => 'participantes',
            'data' => ['participantes' => [['nombre' => 'Lia', 'nacimiento' => '2018-03-03']]],
        ])->assertOk();
        foreach (['pago', 'adicionales', 'confirmacion'] as $step) {
            $this->postJson("/api/onboarding/{$draftId}/step", [
                'stepId' => $step,
                'data' => $step === 'pago'
                    ? ['pago' => [
                        'modalidad' => 'completo',
                        'metodo' => 'met_test',
                        'referencia' => $methodType === 'DIRECTO' ? 'REF-ONB' : '',
                    ]]
                    : ['saved' => true],
            ])->assertOk();
        }

        return [$user, $draftId];
    }
}
