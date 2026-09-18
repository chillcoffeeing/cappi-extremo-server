<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('participants')
            ->whereNull('gender')
            ->update(['gender' => 'PREFIERO_NO_DECIR']);

        Schema::table('participants', function (Blueprint $table) {
            $table->string('gender')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('gender')->nullable()->change();
        });
    }
};