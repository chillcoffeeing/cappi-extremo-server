<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'draft_id')) {
            try { Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['draft_id'])); } catch (Throwable) { }
        }
        if (Schema::hasTable('onboarding_drafts') && Schema::hasColumn('onboarding_drafts', 'draft_id')) {
            try { Schema::table('onboarding_drafts', fn (Blueprint $table) => $table->dropUnique(['draft_id'])); } catch (Throwable) { }
        }
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'order_code')) {
            try { Schema::table('orders', fn (Blueprint $table) => $table->dropUnique(['order_code'])); } catch (Throwable) { }
        }
        if (Schema::hasTable('participants') && Schema::hasColumn('participants', 'slug')) {
            try { Schema::table('participants', function (Blueprint $table): void {
                $table->dropUnique(['user_id', 'slug']);
                $table->dropIndex(['user_id', 'data_completed']);
            }); } catch (Throwable) { }
        }
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'order_id')) {
            try { Schema::table('payments', fn (Blueprint $table) => $table->dropIndex(['order_id'])); } catch (Throwable) { }
        }
        if (Schema::hasTable('onboarding_drafts') && Schema::hasColumn('onboarding_drafts', 'user_id')) {
            try { Schema::table('onboarding_drafts', fn (Blueprint $table) => $table->dropIndex(['user_id', 'status'])); } catch (Throwable) { }
        }

        if (Schema::hasTable('enrollments') && Schema::hasColumn('enrollments', 'session_id')) {
            if (! Schema::hasColumn('enrollments', 'session_uuid')) {
                Schema::table('enrollments', function (Blueprint $table): void {
                    $table->uuid('session_uuid')->nullable();
                });
            }
            // Varios enrollments comparten un mismo session_id (p. ej. hermanos
            // inscritos en la misma semana): se genera un uuid por valor de
            // session_id distinto, no uno por fila, para no romper ese agrupamiento.
            $sessionUuidsByLegacyId = [];
            DB::table('enrollments')->whereNull('session_uuid')->orderBy('id')->eachById(
                function (object $row) use (&$sessionUuidsByLegacyId): void {
                    $sessionUuidsByLegacyId[$row->session_id] ??= (string) Str::uuid();
                    DB::table('enrollments')->where('id', $row->id)->update([
                        'session_uuid' => $sessionUuidsByLegacyId[$row->session_id],
                    ]);
                },
            );
        }

        $columns = [
            'users' => ['draft_id'],
            'participants' => ['user_id', 'slug'],
            'onboarding_drafts' => ['user_id', 'draft_id'],
            'enrollments' => ['participant_id', 'plan_id', 'session_id'],
            'orders' => ['user_id', 'order_code'],
            'payments' => ['user_id', 'order_id'],
            'refresh_tokens' => ['user_id', 'access_token_id'],
            'portal_auth_tokens' => ['user_id'],
        ];

        foreach ($columns as $tableName => $dropColumns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $existing = array_values(array_filter($dropColumns, fn (string $column): bool => Schema::hasColumn($tableName, $column)));
            foreach ($existing as $column) {
                try {
                    Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($column));
                } catch (Throwable $exception) {
                    if (Schema::hasColumn($tableName, $column)) {
                        throw $exception;
                    }
                }
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('La eliminación de identificadores legacy es irreversible. Restaura un backup para volver atrás.');
    }
};
