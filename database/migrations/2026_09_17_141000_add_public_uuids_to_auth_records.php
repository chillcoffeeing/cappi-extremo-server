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
        foreach (['refresh_tokens', 'portal_auth_tokens'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'uuid')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->uuid('uuid')->nullable()->unique();
            });

            DB::table($tableName)->whereNull('uuid')->orderBy('id')->eachById(
                function (object $record) use ($tableName): void {
                    DB::table($tableName)
                        ->where('id', $record->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                },
            );
        }
    }

    public function down(): void
    {
        foreach (['refresh_tokens', 'portal_auth_tokens'] as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropUnique(['uuid']);
                    $table->dropColumn('uuid');
                });
            }
        }
    }
};
