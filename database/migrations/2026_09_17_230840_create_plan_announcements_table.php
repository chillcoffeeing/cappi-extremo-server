<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_announcements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('plan_uuid');
            $table->string('title');
            $table->text('body');
            $table->string('severity')->default('INFO');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('plan_uuid')->references('uuid')->on('plans')->cascadeOnDelete();
            $table->foreign('created_by')->references('uuid')->on('admin_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_announcements');
    }
};
