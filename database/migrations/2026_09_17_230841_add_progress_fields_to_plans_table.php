<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('progress_mode')->default('AUTO')->after('status');
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('progress_mode');
            $table->uuid('current_day_uuid')->nullable()->after('progress_percent');
            $table->string('progress_label')->nullable()->after('current_day_uuid');
            $table->text('progress_note')->nullable()->after('progress_label');
            $table->uuid('progress_updated_by')->nullable()->after('progress_note');
            $table->timestamp('progress_updated_at')->nullable()->after('progress_updated_by');

            $table->foreign('current_day_uuid')->references('uuid')->on('plan_days')->nullOnDelete();
            $table->foreign('progress_updated_by')->references('uuid')->on('admin_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropForeign(['current_day_uuid']);
            $table->dropForeign(['progress_updated_by']);
            $table->dropColumn([
                'progress_mode', 'progress_percent', 'current_day_uuid',
                'progress_label', 'progress_note', 'progress_updated_by', 'progress_updated_at',
            ]);
        });
    }
};
