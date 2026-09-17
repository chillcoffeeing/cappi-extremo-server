<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('venue');
            $table->string('season');
            $table->string('status')->default('PUBLICADO');
            $table->string('cover_url')->nullable();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->unsignedInteger('capacity');
            $table->decimal('price', 16, 4);
            $table->string('currency', 3)->default('USD');
            $table->unsignedTinyInteger('age_min');
            $table->unsignedTinyInteger('age_max');
            $table->text('description')->nullable();
            $table->json('activities')->nullable();
            $table->json('staff')->nullable();
            $table->json('mini_market')->nullable();
            $table->string('whatsapp')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
