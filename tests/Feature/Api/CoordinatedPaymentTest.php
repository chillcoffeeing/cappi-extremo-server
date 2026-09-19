<?php

namespace Tests\Feature\Api;

use App\Actions\Payments\ApprovePayment;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * F-023: `PaymentMethod.type` pasa de un catalogo cerrado de proveedores
 * (ZELLE/EFECTIVO/TRANSFERENCIA_BS) a un enum de comportamiento
 * (DIRECTO|COORDINADO_REMOTO). Un metodo COORDINADO_REMOTO deja el primer
 * pago pendiente de coordinar fuera de la app (ej. WhatsApp): el
 * onboarding igual crea el Payment (sin referencia ni comprobante) y el
 * balance expone los textos/link configurados mientras siga pendiente.
 */
class CoordinatedPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(?string $whatsapp = null): Plan
    {
        return Plan::create([
            'name' => 'Plan de prueba',
            'venue' => 'Sede de prueba',
            'season' => 'Temporada de prueba',
            'starts_at' => '2026-12-07',
            'ends_at' => '2026-12-11',
            'capacity' => 20,
            'price' => 150,
            'age_min' => 5,
            'age_max' => 15,
            'status' => 'PUBLICADO',
            'whatsapp' => $whatsapp,
        ]);
    }

    public function test_methods_endpoint_resolves_whatsapp_link_from_plan_when_method_has_none(): void
    {
        $this->makePlan('584121234567');
        PaymentMethod::create([
            'code' => 'met_zelle',
            'type' => 'COORDINADO_REMOTO',
            'name' => 'Zelle',
            'description' => 'Coordina tu primer pago por WhatsApp.',
            'data' => [
                'whatsapp' => [
                    'mensajeOnboarding' => 'Te escribiremos por WhatsApp para coordinar tu primer pago.',
                    'mensajeDashboard' => 'Coordina tu primer pago por WhatsApp.',
                    'linkTexto' => 'Escríbenos por WhatsApp',
                    'link' => '',
                ],
            ],
            'active' => true,
        ]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/config/metodos-pago')
            ->assertOk()
            ->assertJsonPath('0.tipo', 'COORDINADO_REMOTO')
            ->assertJsonPath('0.datos.whatsapp.link', 'https://wa.me/584121234567')
            ->assertJsonPath('0.datos.whatsapp.linkTexto', 'Escríbenos por WhatsApp');
    }

    public function test_methods_endpoint_keeps_configured_link_when_present(): void
    {
        $this->makePlan('584121234567');
        PaymentMethod::create([
            'code' => 'met_zelle',
            'type' => 'COORDINADO_REMOTO',
            'name' => 'Zelle',
            'description' => 'Coordina tu primer pago por WhatsApp.',
            'data' => [
                'whatsapp' => [
                    'mensajeOnboarding' => '',
                    'mensajeDashboard' => '',
                    'linkTexto' => '',
                    'link' => 'https://wa.me/1234567890',
                ],
            ],
            'active' => true,
        ]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/config/metodos-pago')
            ->assertOk()
            ->assertJsonPath('0.datos.whatsapp.link', 'https://wa.me/1234567890');
    }

    public function test_onboarding_completion_creates_payment_without_reference_for_coordinated_remote_method(): void
    {
        $plan = $this->makePlan();
        PaymentMethod::create([
            'code' => 'met_zelle',
            'type' => 'COORDINADO_REMOTO',
            'name' => 'Zelle',
            'description' => 'Coordina tu primer pago por WhatsApp.',
            'data' => ['whatsapp' => ['mensajeOnboarding' => '', 'mensajeDashboard' => '', 'linkTexto' => '', 'link' => '']],
            'active' => true,
        ]);
        $user = User::factory()->create();
        $draftId = (string) Str::uuid();
        Sanctum::actingAs($user);

        $this->postJson("/api/onboarding/{$draftId}/step", [
            'stepId' => 'cuenta',
            'data' => ['planId' => $plan->uuid],
        ])->assertOk();

        foreach (['pago', 'adicionales', 'confirmacion'] as $step) {
            $this->postJson("/api/onboarding/{$draftId}/step", [
                'stepId' => $step,
                'data' => $step === 'pago'
                    ? ['pago' => ['modalidad' => 'completo', 'metodo' => 'met_zelle', 'referencia' => '']]
                    : ['saved' => true],
            ])->assertOk();
        }

        $this->postJson("/api/onboarding/{$draftId}/step", [
            'stepId' => 'participantes',
            'data' => ['participantes' => [['nombre' => 'Ana', 'nacimiento' => '2018-01-01']]],
        ])->assertOk();

        $this->postJson("/api/onboarding/{$draftId}/complete")
            ->assertOk()
            ->assertJsonPath('status', 'COMPLETADO');

        $this->assertDatabaseHas('payments', [
            'user_uuid' => $user->uuid,
            'method_code' => 'met_zelle',
            'status' => 'PENDIENTE_VERIFICACION',
            'reference' => null,
        ]);
    }

    public function test_balance_exposes_pending_coordinated_payment_and_clears_once_approved(): void
    {
        $this->makePlan('584121234567');
        $method = PaymentMethod::create([
            'code' => 'met_zelle',
            'type' => 'COORDINADO_REMOTO',
            'name' => 'Zelle',
            'description' => 'Coordina tu primer pago por WhatsApp.',
            'data' => [
                'whatsapp' => [
                    'mensajeOnboarding' => 'Mensaje onboarding',
                    'mensajeDashboard' => 'Mensaje dashboard',
                    'linkTexto' => 'Escríbenos',
                    'link' => '',
                ],
            ],
            'active' => true,
        ]);
        $user = User::factory()->create();
        $order = $user->orders()->create([
            'user_uuid' => $user->uuid,
            'ordered_at' => now()->toDateString(),
            'items' => [],
            'total' => 150,
            'paid' => 0,
            'status' => 'PENDIENTE_PAGO',
            'is_registration' => true,
        ]);
        $payment = Payment::create([
            'user_uuid' => $user->uuid,
            'order_uuid' => $order->uuid,
            'paid_at' => now()->toDateString(),
            'amount' => 150,
            'currency' => 'USD',
            'method_code' => $method->code,
            'method_name' => $method->name,
            'reference' => null,
            'concept' => 'Inscripción - Plan de prueba',
            'status' => 'PENDIENTE_VERIFICACION',
            'receipt_name' => 'Pago coordinado por WhatsApp',
            'idempotency_hash' => hash('sha256', 'coordinated-test'),
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/pagos/balance')
            ->assertOk()
            ->assertJsonPath('primerPagoCoordinado.metodoNombre', 'Zelle')
            ->assertJsonPath('primerPagoCoordinado.mensajeDashboard', 'Mensaje dashboard')
            ->assertJsonPath('primerPagoCoordinado.link', 'https://wa.me/584121234567');

        $admin = \App\Models\AdminUser::factory()->create();
        app(ApprovePayment::class)->handle($payment, $admin);

        $this->getJson('/api/pagos/balance')
            ->assertOk()
            ->assertJsonPath('primerPagoCoordinado', null);
    }
}
