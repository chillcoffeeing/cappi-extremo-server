<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone')->nullable()->after('email');
            $table->string('identification')->nullable()->unique()->after('phone');
            $table->string('role')->default('USER')->after('identification');
            $table->string('onboarding_status')->default('INCOMPLETO')->after('role');
            $table->string('draft_id')->nullable()->unique()->after('onboarding_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['identification']);
            $table->dropUnique(['draft_id']);
            $table->dropColumn([
                'phone',
                'identification',
                'role',
                'onboarding_status',
                'draft_id',
            ]);
        });
    }
};
