<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('cargos')->insertOrIgnore([
            'nombre' => 'REPARTIDOR',
            'descripcion' => 'Encargado de la logística y entrega de pedidos',
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
        DB::table('cargos')->where('nombre', 'REPARTIDOR')->delete();
    }
};
