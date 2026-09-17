<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('paid_at');
            $table->decimal('amount', 16, 4);
            $table->string('currency', 3)->default('USD');
            $table->string('method_code');
            $table->string('method_name');
            $table->string('reference')->nullable();
            $table->string('concept')->nullable();
            $table->string('status')->default('PENDIENTE_VERIFICACION');
            $table->text('rejection_reason')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('receipt_name');
            $table->string('order_id')->nullable()->index();
            $table->string('idempotency_hash', 64)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
