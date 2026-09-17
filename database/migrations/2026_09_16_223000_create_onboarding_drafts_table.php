<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('draft_id')->unique();
            $table->unsignedInteger('version')->default(0);
            $table->json('completed_steps')->nullable();
            $table->json('data')->nullable();
            $table->string('status')->default('INCOMPLETO');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_drafts');
    }
};
