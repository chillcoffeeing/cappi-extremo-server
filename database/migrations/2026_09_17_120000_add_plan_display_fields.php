<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->string('date_label')->nullable()->after('ends_at');
            $table->string('duration_label')->nullable()->after('date_label');
            $table->string('schedule')->nullable()->after('duration_label');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn(['date_label', 'duration_label', 'schedule']);
        });
    }
};