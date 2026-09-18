<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Mismos defaults que traia platform_settings (F-002): un plan
            // nuevo se comporta igual que antes sin necesitar configuracion
            // explicita. El valor real de planes ya existentes lo copia la
            // migracion de datos siguiente.
            $table->boolean('sibling_discount_enabled')->default(true)->after('currency');
            $table->unsignedInteger('sibling_discount_min_participants')->default(2)->after('sibling_discount_enabled');
            $table->decimal('sibling_discount_amount', 16, 4)->default(20)->after('sibling_discount_min_participants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['sibling_discount_enabled', 'sibling_discount_min_participants', 'sibling_discount_amount']);
        });
    }
};
