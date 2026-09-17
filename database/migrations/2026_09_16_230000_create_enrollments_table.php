<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('plan_name');
            $table->string('session_id');
            $table->string('session_name');
            $table->string('status')->default('PENDIENTE_PAGO');
            $table->string('plan_type')->default('INDIVIDUAL');
            $table->decimal('total_amount', 16, 4);
            $table->decimal('sibling_discount', 16, 4)->default(0);
            $table->json('payment_method')->nullable();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
