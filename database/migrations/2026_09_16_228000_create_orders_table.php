<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('order_code')->unique();
            $table->date('ordered_at');
            $table->json('items');
            $table->decimal('total', 16, 4);
            $table->decimal('paid', 16, 4)->default(0);
            $table->string('status')->default('PENDIENTE_PAGO');
            $table->boolean('is_registration')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
