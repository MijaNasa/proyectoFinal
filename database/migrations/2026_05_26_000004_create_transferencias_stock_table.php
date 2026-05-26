<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferencias_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('libro_id')->constrained('libros');
            $table->foreignId('sucursal_origen_id')->constrained('sucursales');
            $table->foreignId('sucursal_destino_id')->constrained('sucursales');
            $table->integer('cantidad');
            $table->text('motivo')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->date('fecha')->index();
            $table->timestamps();
        });

        // Tipos de movimiento necesarios
        $tipos = [
            ['codigo' => 'INGRESO_MANUAL',       'nombre' => 'Ingreso Manual',         'descripcion' => 'Carga manual de stock'],
            ['codigo' => 'TRANSFERENCIA_SALIDA',  'nombre' => 'Transferencia - Salida', 'descripcion' => 'Salida por transferencia a otra sucursal'],
            ['codigo' => 'TRANSFERENCIA_ENTRADA', 'nombre' => 'Transferencia - Entrada','descripcion' => 'Entrada por transferencia desde otra sucursal'],
            ['codigo' => 'AJUSTE',                'nombre' => 'Ajuste de Inventario',   'descripcion' => 'Ajuste manual de inventario'],
        ];

        foreach ($tipos as $tipo) {
            DB::table('tipos_movimientos_stock')->insertOrIgnore(array_merge($tipo, [
                'afecta_stock' => true,
                'activo'       => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencias_stock');
    }
};
