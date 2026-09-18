<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-017: el descuento por hermanos deja de leerse en runtime desde la
     * fila unica `platform_settings` y pasa a ser configuracion por plan.
     * Esta migracion de datos copia el valor VIGENTE del singleton (si
     * existe) a todos los planes ya creados, para que su comportamiento no
     * cambie con el despliegue: un plan que hoy aplica el descuento sigue
     * aplicandolo con el mismo minimo/monto que tenia platform_settings.
     * Los planes nuevos creados despues de esta migracion usan los defaults
     * de columna (iguales a los defaults historicos de platform_settings),
     * no este valor copiado.
     */
    public function up(): void
    {
        $settings = DB::table('platform_settings')->first();

        if ($settings === null) {
            return;
        }

        DB::table('plans')->update([
            'sibling_discount_enabled' => $settings->sibling_discount_enabled,
            'sibling_discount_min_participants' => $settings->sibling_discount_min_participants,
            'sibling_discount_amount' => $settings->sibling_discount_amount,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * Migracion de datos: no hay un estado previo por-plan al que volver
     * (los planes no tenian estas columnas antes de la migracion anterior).
     */
    public function down(): void {}
};
