<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->string('shirt_size')->nullable()->after('photo_url');
            $table->decimal('weight_kg', 6, 2)->nullable()->after('shirt_size');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->dropColumn(['shirt_size', 'weight_kg']);
        });
    }
};
