<?php

namespace Tests\Feature\Api;

use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParticipantsWizardStepValidationTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        return $user;
    }

    private function postStep(string $participantId, string $stepId, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/participantes/{$participantId}/wizard/step", [
            'stepId' => $stepId,
            'data' => $data,
        ]);
    }

    public function test_validation_errors_use_laravel_422_shape_with_data_prefix(): void
    {
        $user = $this->actingUser();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);

        $this->postStep($participant->uuid, 'datos-basicos', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors([
                'data.nombre',
                'data.fechaNacimiento',
                'data.genero',
                'data.cedula',
                'data.tallaCamisa',
                'data.pesoKg',
            ]);
    }

    public function test_datos_basicos_complete_payload_is_accepted_and_persisted(): void
    {
        $user = $this->actingUser();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);

        $payload = [
            'nombre' => 'Juan Perez',
            'fechaNacimiento' => '2018-03-12',
            'genero' => 'MASCULINO',
            'cedula' => '001-1234567-1',
            'tallaCamisa' => 'CH',
            'pesoKg' => 28,
        ];

        $this->postStep($participant->uuid, 'datos-basicos', $payload)
            ->assertOk()
            ->assertJsonPath('data.datos-basicos.nombre', 'Juan Perez');

        $this->assertSame('MASCULINO', $participant->refresh()->wizard_steps['datos-basicos']['genero']);
    }

    public function test_gender_only_accepts_enum_values(): void
    {
        $user = $this->actingUser();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);

        $payload = fn (mixed $genero): array => [
            'nombre' => 'Juan Perez',
            'fechaNacimiento' => '2018-03-12',
            'genero' => $genero,
            'cedula' => '001-1234567-1',
            'tallaCamisa' => 'CH',
            'pesoKg' => 28,
        ];

        $this->postStep($participant->uuid, 'datos-basicos', $payload('NO_VALIDO'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('data.genero');

        $this->postStep($participant->uuid, 'datos-basicos', $payload('PREFIERO_NO_DECIR'))
            ->assertOk();
    }

    public function test_salud_step_is_fully_optional(): void
    {
        $user = $this->actingUser();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);

        $this->postStep($participant->uuid, 'salud', [])->assertOk();

        $this->postStep($participant->uuid, 'salud', [
            'tipoSangre' => 'O+',
            'alergias' => 'Polen',
            'condicionesMedicas' => 'Asma leve',
            'medicamentos' => '',
            'discapacidades' => '',
            'infoAdicional' => 'Usa inhalador',
            'requiereAcompanante' => true,
        ])->assertOk();
    }

    public function test_contactos_emergencia_requires_at_least_one_contact(): void
    {
        $user = $this->actingUser();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);

        $this->postStep($participant->uuid, 'contactos-emergencia', ['contactosEmergencia' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('data.contactosEmergencia');
    }

    public function test_contactos_emergencia_requires_contact_fields(): void
    {
        $user = $this->actingUser();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);

        $this->postStep($participant->uuid, 'contactos-emergencia', [
            'contactosEmergencia' => [[
                'id' => 'c_1',
                'nombre' => '',
                'telefono' => '',
                'parentesco' => 'Otro',
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['data.contactosEmergencia.0.nombre', 'data.contactosEmergencia.0.telefono']);
    }

    public function test_contactos_emergencia_accepts_new_parentesco_opciones(): void
    {
        $user = $this->actingUser();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);

        $this->postStep($participant->uuid, 'contactos-emergencia', [
            'contactosEmergencia' => [[
                'id' => 'c_1',
                'nombre' => 'Carlos Perez',
                'telefono' => '8095550102',
                'parentesco' => 'Padrino',
            ]],
        ])->assertOk();

        $this->postStep($participant->uuid, 'contactos-emergencia', [
            'contactosEmergencia' => [[
                'id' => 'c_2',
                'nombre' => 'Luis Perez',
                'telefono' => '8095550103',
                'parentesco' => 'Tío',
            ]],
        ])->assertOk();
    }

    public function test_encargado_retiro_null_and_empty_structure_are_valid(): void
    {
        $user = $this->actingUser();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);

        $this->postStep($participant->uuid, 'encargado-retiro', ['encargadoRetiro' => null])->assertOk();

        $this->postStep($participant->uuid, 'encargado-retiro', [
            'encargadoRetiro' => ['id' => 'er_x', 'nombre' => '', 'documento' => '001', 'telefono' => '', 'relacion' => ''],
        ])->assertOk();

        $steps = $participant->refresh()->wizard_steps;
        $this->assertNull($steps['encargado-retiro']['encargadoRetiro']);
    }

    public function test_encargado_retiro_partial_is_rejected(): void
    {
        $user = $this->actingUser();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);

        $this->postStep($participant->uuid, 'encargado-retiro', [
            'encargadoRetiro' => ['nombre' => 'Ana Perez', 'telefono' => ''],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['data.encargadoRetiro.telefono', 'data.encargadoRetiro.relacion']);
    }

    public function test_encargado_retiro_complete_with_padrino_is_accepted(): void
    {
        $user = $this->actingUser();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);

        $this->postStep($participant->uuid, 'encargado-retiro', [
            'encargadoRetiro' => [
                'id' => 'er_1',
                'nombre' => 'Carla Perez',
                'documento' => '001-7654321-8',
                'telefono' => '8095550104',
                'relacion' => 'Padrino',
                'esContactoEmergencia' => false,
            ],
        ])->assertOk();
    }

    public function test_seguro_medico_no_tiene_and_empty_are_accepted(): void
    {
        $user = $this->actingUser();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);

        $this->postStep($participant->uuid, 'seguro-medico', ['noTiene' => true])->assertOk();

        $this->postStep($participant->uuid, 'seguro-medico', [
            'aseguradora' => 'Seguros Universal',
            'poliza' => 'POL-8899',
            'telefonoEmergencias' => '8095550199',
            'noTiene' => false,
        ])->assertOk();
    }
}