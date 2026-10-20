<?php

namespace Tests\Feature\Api;

use App\Filament\Resources\Participants\RelationManagers\CorrectionRequestsRelationManager;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * F-061: ficha del participante. Compatibilidad con datos reales y con el
 * portal viejo cacheado (progress/compat-rules-2026-10-19.md).
 */
class ParticipantCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private const JSON = ['Accept' => 'application/json'];

    /** @return array{0: User, 1: Participant} */
    private function familyWithParticipant(array $attributes = []): array
    {
        $user = User::factory()->create();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid, ...$attributes]);
        Sanctum::actingAs($user);

        return [$user, $participant];
    }

    public function test_old_portal_patch_with_absolute_photo_url_is_ignored_and_photo_survives(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('participants/kid.jpg', 'img');
        [, $participant] = $this->familyWithParticipant(['photo_url' => 'participants/kid.jpg']);

        $absolute = Storage::disk('public')->url('participants/kid.jpg');

        // Shape del portal viejo: manda datosBasicos completos, incluido photoUrl.
        $this->patchJson("/api/participantes/{$participant->uuid}", [
            'name' => 'Nombre Corregido',
            'photoUrl' => $absolute,
            'shirtSize' => 'L',
        ], self::JSON)
            ->assertOk()
            ->assertJsonPath('data.datosBasicos.nombre', 'Nombre Corregido')
            ->assertJsonPath('data.datosBasicos.tallaCamisa', 'L')
            ->assertJsonPath('data.datosBasicos.fotoUrl', $absolute);

        $this->assertSame('participants/kid.jpg', $participant->fresh()->photo_url);
        Storage::disk('public')->assertExists('participants/kid.jpg');
    }

    public function test_patch_with_traversal_or_foreign_photo_url_is_ignored_without_error(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('product-images/catalogo.jpg', 'img');
        [, $participant] = $this->familyWithParticipant();

        foreach (['http://x/../../product-images/catalogo.jpg', '../../../../etc/passwd', 'no-es-url', null] as $value) {
            $this->patchJson("/api/participantes/{$participant->uuid}", ['photoUrl' => $value], self::JSON)
                ->assertOk()
                ->assertJsonPath('data.datosBasicos.fotoUrl', null);
        }

        $this->assertNull($participant->fresh()->photo_url);

        // Subir una foto despues no borra nada ajeno.
        $this->post("/api/participantes/{$participant->uuid}/foto", [
            '_method' => 'PUT',
            'foto' => UploadedFile::fake()->image('carnet.jpg'),
        ], self::JSON)->assertOk();

        Storage::disk('public')->assertExists('product-images/catalogo.jpg');
    }

    public function test_upload_replaces_only_own_previous_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('participants/old.jpg', 'img');
        Storage::disk('public')->put('product-images/catalogo.jpg', 'img');
        [, $participant] = $this->familyWithParticipant(['photo_url' => 'participants/old.jpg']);

        $this->post("/api/participantes/{$participant->uuid}/foto", [
            '_method' => 'PUT',
            'foto' => UploadedFile::fake()->image('nueva.jpg'),
        ], self::JSON)->assertOk();

        Storage::disk('public')->assertMissing('participants/old.jpg');
        $new = $participant->fresh()->photo_url;
        $this->assertStringStartsWith('participants/', $new);
        Storage::disk('public')->assertExists($new);

        // Un valor legado manipulado fuera de participants/ nunca se borra.
        $participant->forceFill(['photo_url' => 'participants/../product-images/catalogo.jpg'])->save();
        $this->post("/api/participantes/{$participant->uuid}/foto", [
            '_method' => 'PUT',
            'foto' => UploadedFile::fake()->image('otra.jpg'),
        ], self::JSON)->assertOk();
        Storage::disk('public')->assertExists('product-images/catalogo.jpg');
    }

    public function test_photo_url_uses_public_disk_and_does_not_nest_legacy_absolute_urls(): void
    {
        config(['filesystems.default' => 'public']);
        [, $participant] = $this->familyWithParticipant(['photo_url' => 'participants/kid.jpg']);

        $this->getJson("/api/participantes/{$participant->uuid}", self::JSON)
            ->assertOk()
            ->assertJsonPath('data.datosBasicos.fotoUrl', Storage::disk('public')->url('participants/kid.jpg'));

        // Guard idempotente: un dato legado absoluto se devuelve tal cual.
        $participant->forceFill(['photo_url' => 'https://cdn.example.test/kid.jpg'])->save();
        $this->getJson("/api/participantes/{$participant->uuid}", self::JSON)
            ->assertOk()
            ->assertJsonPath('data.datosBasicos.fotoUrl', 'https://cdn.example.test/kid.jpg');
    }

    public function test_deleting_an_emergency_contact_removes_it(): void
    {
        $ana = ['id' => 'c1', 'nombre' => 'Ana', 'telefono' => '0414-1', 'parentesco' => 'Madre'];
        $beto = ['id' => 'c2', 'nombre' => 'Beto', 'telefono' => '0414-2', 'parentesco' => 'Padre'];
        [, $participant] = $this->familyWithParticipant(['emergency_contacts' => [$ana, $beto]]);

        $this->patchJson("/api/participantes/{$participant->uuid}", ['emergencyContacts' => [$beto]], self::JSON)
            ->assertOk()
            ->assertJsonCount(1, 'data.contactosEmergencia')
            ->assertJsonPath('data.contactosEmergencia.0.nombre', 'Beto');

        $this->assertSame([$beto], $participant->fresh()->emergency_contacts);
    }

    public function test_existing_contacts_survive_patches_of_other_sections(): void
    {
        $ana = ['id' => 'c1', 'nombre' => 'Ana', 'telefono' => '0414-1', 'parentesco' => 'Madre'];
        [, $participant] = $this->familyWithParticipant([
            'emergency_contacts' => [$ana],
            'health' => ['alergias' => 'Mani', 'tipoSangre' => 'O+'],
        ]);

        $this->patchJson("/api/participantes/{$participant->uuid}", ['health' => ['tipoSangre' => 'A+']], self::JSON)
            ->assertOk()
            ->assertJsonPath('data.contactosEmergencia.0.nombre', 'Ana')
            // health conserva el merge de objeto: las claves omitidas se mantienen.
            ->assertJsonPath('data.salud.alergias', 'Mani')
            ->assertJsonPath('data.salud.tipoSangre', 'A+');
    }

    public function test_no_insurance_replaces_previous_insurer(): void
    {
        [, $participant] = $this->familyWithParticipant([
            'medical_insurance' => ['aseguradora' => 'Seguros X', 'poliza' => '123', 'noTiene' => false],
        ]);

        $this->patchJson("/api/participantes/{$participant->uuid}", ['medicalInsurance' => ['noTiene' => true]], self::JSON)
            ->assertOk()
            ->assertJsonPath('data.seguroMedico', ['noTiene' => true]);

        // Sin noTiene sigue el merge de objeto (portal viejo que manda parcial).
        $this->patchJson("/api/participantes/{$participant->uuid}", ['medicalInsurance' => ['aseguradora' => 'Y', 'noTiene' => false]], self::JSON)
            ->assertOk()
            ->assertJsonPath('data.seguroMedico.aseguradora', 'Y');
    }

    public function test_pickup_contact_null_removes_it_and_object_replaces_it(): void
    {
        [, $participant] = $this->familyWithParticipant([
            'pickup_contact' => ['id' => 'er1', 'nombre' => 'Tia', 'documento' => 'V1', 'telefono' => '1', 'relacion' => 'Tío', 'esContactoEmergencia' => false],
        ]);

        $this->patchJson("/api/participantes/{$participant->uuid}", ['pickupContact' => null], self::JSON)
            ->assertOk()
            ->assertJsonPath('data.encargadoRetiro', null);
        $this->assertNull($participant->fresh()->pickup_contact);

        $pickup = ['id' => 'er2', 'nombre' => 'Abuela', 'documento' => 'V2', 'telefono' => '2', 'relacion' => 'Abuelo/a', 'esContactoEmergencia' => false];
        $this->patchJson("/api/participantes/{$participant->uuid}", ['pickupContact' => $pickup], self::JSON)
            ->assertOk()
            ->assertJsonPath('data.encargadoRetiro.nombre', 'Abuela');
    }

    public function test_wizard_state_exposes_saved_steps_to_resume(): void
    {
        [, $participant] = $this->familyWithParticipant();

        $this->postJson("/api/participantes/{$participant->uuid}/wizard/step", [
            'stepId' => 'datos-basicos',
            'data' => ['nombre' => 'Sofia', 'fechaNacimiento' => '2015-01-01', 'genero' => 'FEMENINO', 'cedula' => 'V1', 'tallaCamisa' => 'S', 'pesoKg' => 30],
        ], self::JSON)->assertOk();

        $this->getJson("/api/participantes/{$participant->uuid}/wizard", self::JSON)
            ->assertOk()
            // Sin wrapper: el resource ya tiene una clave `data` (los pasos).
            ->assertJsonPath('participantId', $participant->uuid)
            ->assertJsonPath('completedSteps', ['datos-basicos'])
            ->assertJsonPath('data.datos-basicos.nombre', 'Sofia');
    }

    public function test_admin_no_longer_offers_documents_section_but_keeps_label_for_old_requests(): void
    {
        $this->assertArrayNotHasKey('documentos', CorrectionRequestsRelationManager::sectionOptions());
        $this->assertSame('Documentos', CorrectionRequestsRelationManager::sectionLabels()['documentos']);
    }
}
