<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('tipos_movimientos_stock')) {
            return;
        }

        DB::table('tipos_movimientos_stock')->insertOrIgnore([
            'codigo' => 'EGRESO_MANUAL',
            'nombre' => 'Egreso Manual',
            'descripcion' => 'Descarga manual de stock',
            'afecta_stock' => true,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tipos_movimientos_stock')->where('codigo', 'EGRESO_MANUAL')->delete();
    }
};
