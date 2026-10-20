<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-053 — control de borrado del SUPER_ADMIN.
 *
 * SOLO cambia reglas de FK y agrega una columna nullable. No hay DELETE ni
 * UPDATE de datos y no se limpian huerfanos existentes (decision del usuario
 * 2026-10-19). Ver progress/plan_2026-10-19_admin-borrado.md.
 *
 * - participants.user_uuid, onboarding_drafts.user_uuid,
 *   enrollments.participant_uuid, refresh_tokens.user_uuid y
 *   portal_auth_tokens.user_uuid pasan de nullOnDelete a cascadeOnDelete.
 * - orders.user_uuid, payments.user_uuid, payments.order_uuid y
 *   enrollments.plan_uuid quedan como estan (nullOnDelete): ordenes y pagos
 *   se conservan siempre.
 * - orders.representative_snapshot y payments.representative_snapshot (JSON,
 *   nullable): solo DeleteRepresentative las rellena.
 *
 * Fuera de transaccion por prudencia: en SQLite, cambiar una FK reconstruye
 * la tabla (create temp + insert + drop + rename) y Laravel desactiva
 * `PRAGMA foreign_keys` para que el DROP no dispare acciones ON DELETE en las
 * tablas hijas; ese PRAGMA no tiene efecto dentro de una transaccion. En
 * MySQL el DDL no es transaccional, asi que no cambia nada. El test
 * CascadeDeleteMigrationTest compara todas las filas antes y despues.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    /** @var array<string, array{0: string, 1: string}> tabla => [columna, tabla referenciada] */
    private const CASCADE_FOREIGN_KEYS = [
        'participants' => ['user_uuid', 'users'],
        'onboarding_drafts' => ['user_uuid', 'users'],
        'enrollments' => ['participant_uuid', 'participants'],
        'refresh_tokens' => ['user_uuid', 'users'],
        'portal_auth_tokens' => ['user_uuid', 'users'],
    ];

    private const SNAPSHOT_TABLES = ['orders', 'payments'];

    public function up(): void
    {
        foreach (self::CASCADE_FOREIGN_KEYS as $tableName => [$column, $referencedTable]) {
            Schema::table($tableName, function (Blueprint $table) use ($column, $referencedTable): void {
                $table->dropForeign([$column]);
                $table->foreign($column)->references('uuid')->on($referencedTable)->cascadeOnDelete();
            });
        }

        foreach (self::SNAPSHOT_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->json('representative_snapshot')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::SNAPSHOT_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('representative_snapshot');
            });
        }

        foreach (self::CASCADE_FOREIGN_KEYS as $tableName => [$column, $referencedTable]) {
            Schema::table($tableName, function (Blueprint $table) use ($column, $referencedTable): void {
                $table->dropForeign([$column]);
                $table->foreign($column)->references('uuid')->on($referencedTable)->nullOnDelete();
            });
        }
    }
};
