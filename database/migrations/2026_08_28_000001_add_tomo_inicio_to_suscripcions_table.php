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
        Schema::table('suscripcions', function (Blueprint $table) {
            $table->integer('tomo_inicio')->nullable()->default(1)->after('sucursal_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suscripcions', function (Blueprint $table) {
            $table->dropColumn('tomo_inicio');
        });
    }
};
