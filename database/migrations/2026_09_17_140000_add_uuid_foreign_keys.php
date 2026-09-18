<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'participants' => ['user_uuid' => 'users'],
            'onboarding_drafts' => ['user_uuid' => 'users'],
            'enrollments' => ['participant_uuid' => 'participants', 'plan_uuid' => 'plans'],
            'orders' => ['user_uuid' => 'users'],
            'payments' => ['user_uuid' => 'users', 'order_uuid' => 'orders'],
            'portal_auth_tokens' => ['user_uuid' => 'users'],
            'refresh_tokens' => ['user_uuid' => 'users'],
        ];

        foreach ($columns as $tableName => $foreignColumns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($foreignColumns): void {
                foreach ($foreignColumns as $column => $referencedTable) {
                    if (! Schema::hasColumn($table->getTable(), $column)) {
                        $table->uuid($column)->nullable();
                    }
                }
            });

            foreach ($foreignColumns as $column => $referencedTable) {
                if ($tableName === 'enrollments' && $column === 'participant_uuid') {
                    DB::statement('UPDATE enrollments SET participant_uuid = (SELECT uuid FROM participants WHERE participants.id = enrollments.participant_id) WHERE participant_uuid IS NULL');
                } elseif ($tableName === 'enrollments' && $column === 'plan_uuid') {
                    DB::statement('UPDATE enrollments SET plan_uuid = (SELECT uuid FROM plans WHERE plans.id = enrollments.plan_id) WHERE plan_uuid IS NULL');
                } elseif ($tableName === 'payments' && $column === 'order_uuid') {
                    DB::statement('UPDATE payments SET order_uuid = (SELECT uuid FROM orders WHERE orders.order_code = payments.order_id) WHERE order_uuid IS NULL');
                } else {
                    $legacyColumn = str_ends_with($column, '_uuid')
                        ? substr($column, 0, -5).'_id'
                        : null;
                    if ($legacyColumn && Schema::hasColumn($tableName, $legacyColumn)) {
                        DB::statement("UPDATE {$tableName} SET {$column} = (SELECT uuid FROM {$referencedTable} WHERE {$referencedTable}.id = {$tableName}.{$legacyColumn}) WHERE {$column} IS NULL");
                    }
                }
            }

            Schema::table($tableName, function (Blueprint $table) use ($foreignColumns): void {
                foreach ($foreignColumns as $column => $referencedTable) {
                    $table->foreign($column)->references('uuid')->on($referencedTable)->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'participants' => ['user_uuid'],
            'onboarding_drafts' => ['user_uuid'],
            'enrollments' => ['participant_uuid', 'plan_uuid'],
            'orders' => ['user_uuid'],
            'payments' => ['user_uuid', 'order_uuid'],
            'portal_auth_tokens' => ['user_uuid'],
            'refresh_tokens' => ['user_uuid'],
        ] as $tableName => $columns) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }
    }
};
