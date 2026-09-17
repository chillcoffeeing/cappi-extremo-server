<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->decimal('price', 16, 4);
            $table->decimal('previous_price', 16, 4)->nullable();
            $table->json('variants')->nullable();
            $table->json('images')->nullable();
            $table->text('description')->nullable();
            $table->boolean('in_stock')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
