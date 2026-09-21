<?php

namespace Tests\Feature\Api;

use App\Models\Participant;
use App\Models\ParticipantCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParticipantsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_participants_index_requires_authentication(): void
    {
        $this->getJson('/api/participantes')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_authenticated_user_only_receives_own_participants(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Participant::factory()->create([
            'user_uuid' => $user->uuid,
            'name' => 'Juan Perez',
            'birth_date' => '2018-03-12',
            'data_completed' => false,
        ]);
        Participant::factory()->create([
            'user_uuid' => $otherUser->uuid,
            'name' => 'Otro Participante',
            'birth_date' => '2017-01-01',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/participantes')
            ->assertOk()
            ->assertJsonPath('data.0.datosCompletos', false)
            ->assertJsonPath('data.0.datosBasicos.nombre', 'Juan Perez')
            ->assertJsonMissing(['nombre' => 'Otro Participante']);
    }

    public function test_participant_detail_patch_and_wizard_completion_are_persisted(): void
    {
        $user = User::factory()->create();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);
        Sanctum::actingAs($user);

        $this->getJson("/api/participantes/{$participant->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $participant->uuid)
            ->assertJsonPath('data.documentos', []);

        $this->patchJson("/api/participantes/{$participant->uuid}", [
            'health' => ['tipoSangre' => 'O+'],
            'shirtSize' => 'M',
            'weightKg' => 34.5,
        ])->assertOk()
            ->assertJsonPath('data.salud.tipoSangre', 'O+')
            ->assertJsonPath('data.datosBasicos.tallaCamisa', 'M')
            ->assertJsonPath('data.datosBasicos.pesoKg', 34.5);

        $steps = [
            'datos-basicos' => [
                'nombre' => 'Diego Martinez',
                'fechaNacimiento' => '2015-09-27',
                'genero' => 'MASCULINO',
                'cedula' => 'V-12345678',
                'tallaCamisa' => 'M',
                'pesoKg' => 34.5,
            ],
            'salud' => [
                'tipoSangre' => 'O+',
                'alergias' => 'Polen',
                'condicionesMedicas' => 'Asma leve',
                'medicamentos' => 'Salbutamol',
                'discapacidades' => '',
                'requiereAcompanante' => false,
                'infoAdicional' => 'Usa inhalador de rescate',
            ],
            'contactos-emergencia' => [
                'contactosEmergencia' => [[
                    'id' => 'c_1',
                    'nombre' => 'Laura Martinez',
                    'telefono' => '+58 412 555 0202',
                    'parentesco' => 'Madre',
                ]],
            ],
            'encargado-retiro' => [
                'encargadoRetiro' => [
                    'id' => 'er_1',
                    'nombre' => 'Laura Martinez',
                    'documento' => 'V-12345678',
                    'telefono' => '+58 412 555 0202',
                    'relacion' => 'Madre',
                    'esContactoEmergencia' => true,
                ],
            ],
            'seguro-medico' => [
                'aseguradora' => 'Seguro Demo',
                'poliza' => 'POL-001',
                'telefonoEmergencias' => '+58 412 000 0000',
            ],
        ];

        foreach ($steps as $step => $data) {
            $this->postJson("/api/participantes/{$participant->uuid}/wizard/step", [
                'stepId' => $step,
                'data' => $data,
            ])->assertOk();
        }

        $this->postJson("/api/participantes/{$participant->uuid}/wizard/complete")
            ->assertOk()
            ->assertJsonPath('data.datosCompletos', true)
            ->assertJsonPath('data.datosBasicos.tallaCamisa', 'M')
            ->assertJsonPath('data.datosBasicos.pesoKg', 34.5)
            ->assertJsonPath('data.salud.alergias', 'Polen')
            ->assertJsonPath('data.contactosEmergencia.0.nombre', 'Laura Martinez')
            ->assertJsonPath('data.encargadoRetiro.nombre', 'Laura Martinez')
            ->assertJsonPath('data.seguroMedico.aseguradora', 'Seguro Demo');
    }

    public function test_participant_resource_includes_active_enrollment_summary(): void
    {
        $user = User::factory()->create();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);
        $participant->enrollment()->create([
            'plan_name' => 'Plan Vacacional Decembrino',
            'session_uuid' => (string) str()->uuid(),
            'session_name' => 'Semana 1',
            'status' => 'PENDIENTE_PAGO',
            'plan_type' => 'INDIVIDUAL',
            'total_amount' => 150,
            'sibling_discount' => 0,
            'payment_method' => ['tipo' => 'COMPLETO'],
            'starts_at' => '2026-12-07',
            'ends_at' => '2026-12-11',
        ]);
        Sanctum::actingAs($user);

        $this->getJson("/api/participantes/{$participant->uuid}")
            ->assertOk()
            ->assertJsonPath('data.inscripcionActiva.planNombre', 'Plan Vacacional Decembrino')
            ->assertJsonPath('data.inscripcionActiva.estado', 'PENDIENTE_PAGO');
    }

    public function test_participant_resource_exposes_correction_requests(): void
    {
        $user = User::factory()->create();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);
        ParticipantCorrectionRequest::create([
            'participant_uuid' => $participant->uuid,
            'section' => 'salud',
            'message' => 'Falta el tipo de sangre.',
            'status' => 'PENDIENTE',
        ]);
        Sanctum::actingAs($user);

        $this->getJson("/api/participantes/{$participant->uuid}")
            ->assertOk()
            ->assertJsonPath('data.solicitudesCorreccion.0.seccion', 'salud')
            ->assertJsonPath('data.solicitudesCorreccion.0.mensaje', 'Falta el tipo de sangre.')
            ->assertJsonPath('data.solicitudesCorreccion.0.estado', 'PENDIENTE');
    }

    public function test_participant_routes_do_not_expose_another_users_participant(): void
    {
        $user = User::factory()->create();
        $otherParticipant = Participant::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/participantes/{$otherParticipant->uuid}")
            ->assertNotFound();
    }

    public function test_participant_photo_is_stored_as_multipart_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);
        Sanctum::actingAs($user);

        $response = $this->put("/api/participantes/{$participant->uuid}/foto", [
            'foto' => UploadedFile::fake()->image('carnet.jpg'),
        ]);

        $response->assertOk()->assertJsonPath('data.datosBasicos.fotoUrl', fn (string $url): bool => str_contains($url, '/storage/participants/'));
        $this->assertNotEmpty(Storage::disk('public')->allFiles('participants'));
    }
}
