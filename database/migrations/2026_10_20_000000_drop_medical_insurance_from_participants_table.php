<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F-076 — la ficha deja de tener "Seguro médico" (pedido del usuario: purgar
 * el formulario y sus datos). DESTRUCTIVA a propósito: el down() recrea la
 * columna vacía, los datos solo vuelven desde el respaldo previo al despliegue.
 *
 * 1. Solicitudes de corrección PENDIENTES de esa sección -> DESCARTADA con
 *    motivo (la familia ya no tiene donde corregirlo). Las cerradas quedan.
 * 2. `wizard_steps.seguro-medico` (respuestas guardadas del asistente) se quita.
 * 3. Se elimina la columna `participants.medical_insurance`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('participant_correction_requests')
            ->where('section', 'seguroMedico')
            ->where('status', 'PENDIENTE')
            ->update([
                'status' => 'DESCARTADA',
                'resolution' => 'La sección "Seguro médico" se eliminó de la ficha.',
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('participants')
            ->whereNotNull('wizard_steps')
            ->orderBy('id')
            ->select(['id', 'wizard_steps'])
            ->chunkById(200, function ($participants): void {
                foreach ($participants as $participant) {
                    $steps = json_decode((string) $participant->wizard_steps, true);
                    if (is_array($steps) && array_key_exists('seguro-medico', $steps)) {
                        unset($steps['seguro-medico']);
                        DB::table('participants')->where('id', $participant->id)
                            ->update(['wizard_steps' => json_encode($steps === [] ? new stdClass : $steps)]);
                    }
                }
            });

        Schema::table('participants', function (Blueprint $table): void {
            $table->dropColumn('medical_insurance');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->json('medical_insurance')->nullable();
        });
    }
};
