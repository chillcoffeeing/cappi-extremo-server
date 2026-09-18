<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_day_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('plan_day_uuid');
            $table->string('title');
            $table->text('description')->nullable();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('PLANIFICADA');
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('requirements')->nullable();
            $table->timestamps();

            $table->foreign('plan_day_uuid')->references('uuid')->on('plan_days')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_day_activities');
    }
};
