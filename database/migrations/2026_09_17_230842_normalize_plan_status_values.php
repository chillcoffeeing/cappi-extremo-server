<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Antes de Fase 2 (backoffice), `plans.status` solo conocia el valor libre
 * 'ACTIVO' (usado por ConfigController, CompleteOnboarding y
 * RegisterParticipantInscription para resolver "el plan operativo"). Fase 2
 * introduce la maquina de estados documentada en
 * api/docs/backoffice/04-plan-activo-y-contenido.md
 * (BORRADOR -> PUBLICADO -> EN_CURSO -> FINALIZADO, con PAUSADO/CANCELADO).
 * 'ACTIVO' no es parte de esa maquina: se adopta 'PUBLICADO' como equivalente
 * y se actualizan los call sites que buscaban 'ACTIVO' en el mismo cambio.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('plans')->where('status', 'ACTIVO')->update(['status' => 'PUBLICADO']);
    }

    public function down(): void
    {
        DB::table('plans')->where('status', 'PUBLICADO')->update(['status' => 'ACTIVO']);
    }
};
