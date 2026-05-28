<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cierres_caja', function (Blueprint $table) {
            $table->unique(['sucursal_id', 'fecha'], 'cierres_caja_sucursal_fecha_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cierres_caja', function (Blueprint $table) {
            $table->dropUnique('cierres_caja_sucursal_fecha_unique');
        });
    }
};
