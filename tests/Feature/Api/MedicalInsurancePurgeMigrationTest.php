<?php

namespace Tests\Feature\Api;

use App\Models\Participant;
use App\Models\ParticipantCorrectionRequest;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * F-076: la migracion purga el seguro medico (columna, respuestas del
 * asistente y solicitudes pendientes de esa seccion) sin tocar nada mas.
 */
class MedicalInsurancePurgeMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private const MIGRATION = 'migrations/2026_10_20_000000_drop_medical_insurance_from_participants_table.php';

    private function migration(): Migration
    {
        return require database_path(self::MIGRATION);
    }

    public function test_purges_insurance_data_and_keeps_everything_else(): void
    {
        $this->migration()->down();
        $this->assertTrue(Schema::hasColumn('participants', 'medical_insurance'));

        $user = User::factory()->create();
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);
        DB::table('participants')->where('id', $participant->id)->update([
            'medical_insurance' => json_encode(['aseguradora' => 'Seguros X']),
            'wizard_steps' => json_encode([
                'salud' => ['alergias' => 'Polen'],
                'seguro-medico' => ['aseguradora' => 'Seguros X'],
            ]),
        ]);
        $onlyInsurance = Participant::factory()->create(['user_uuid' => $user->uuid]);
        DB::table('participants')->where('id', $onlyInsurance->id)
            ->update(['wizard_steps' => json_encode(['seguro-medico' => ['noTiene' => true]])]);

        $pending = ParticipantCorrectionRequest::create(['participant_uuid' => $participant->uuid, 'section' => 'seguroMedico', 'message' => 'Falta la póliza']);
        $resolved = ParticipantCorrectionRequest::create(['participant_uuid' => $participant->uuid, 'section' => 'seguroMedico', 'message' => 'x', 'status' => 'RESUELTA', 'resolution' => 'ok']);
        $other = ParticipantCorrectionRequest::create(['participant_uuid' => $participant->uuid, 'section' => 'salud', 'message' => 'Revisar alergias']);

        // up() por el Migrator real, en un batch propio: el rollback de
        // DatabaseMigrations solo revierte esta migracion (las legacy de
        // 2026_09_17 son irreversibles; mismo patron que CascadeDeleteMigrationTest).
        DB::table('migrations')->where('migration', basename(self::MIGRATION, '.php'))->delete();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $this->assertFalse(Schema::hasColumn('participants', 'medical_insurance'));
        $this->assertSame(['salud' => ['alergias' => 'Polen']], $participant->fresh()->wizard_steps);
        $this->assertSame([], $onlyInsurance->fresh()->wizard_steps);

        $this->assertSame('DESCARTADA', $pending->fresh()->status);
        $this->assertNotNull($pending->fresh()->resolved_at);
        $this->assertSame('RESUELTA', $resolved->fresh()->status);
        $this->assertSame('ok', $resolved->fresh()->resolution);
        $this->assertSame('PENDIENTE', $other->fresh()->status);
        $this->assertSame(2, Participant::count());
        $this->assertSame(3, ParticipantCorrectionRequest::count());
    }
}
