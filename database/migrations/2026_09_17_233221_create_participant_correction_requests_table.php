<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('participant_uuid');
            $table->string('section');
            $table->text('message');
            $table->string('status')->default('PENDIENTE');
            $table->text('resolution')->nullable();
            $table->uuid('requested_by')->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('participant_uuid')->references('uuid')->on('participants')->cascadeOnDelete();
            $table->foreign('requested_by')->references('uuid')->on('admin_users')->nullOnDelete();
            $table->foreign('resolved_by')->references('uuid')->on('admin_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_correction_requests');
    }
};
