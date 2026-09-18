<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'participants' => ['user_id'],
            'onboarding_drafts' => ['user_id'],
            'payments' => ['user_id'],
            'orders' => ['user_id'],
            'enrollments' => ['participant_id', 'plan_id'],
            'refresh_tokens' => ['user_id', 'access_token_id'],
            'portal_auth_tokens' => ['user_id'],
        ] as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    try {
                        $table->dropForeign([$column]);
                    } catch (Throwable) {
                        // SQLite/MySQL may already have removed a legacy constraint.
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Legacy integer constraints are intentionally not restored. UUID FKs are canonical.
    }
};
