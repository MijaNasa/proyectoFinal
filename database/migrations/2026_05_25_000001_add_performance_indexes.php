<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->unique(['libro_id', 'sucursal_id'], 'stocks_libro_sucursal_unique');
        });

        Schema::table('precios_libros', function (Blueprint $table) {
            $table->index(['libro_id', 'activo'], 'precios_libros_libro_activo_idx');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->index(['user_id', 'tipo'], 'ventas_user_tipo_idx');
            $table->index(['sucursal_id', 'fecha'], 'ventas_sucursal_fecha_idx');
        });

        Schema::table('transacciones', function (Blueprint $table) {
            $table->index(['sucursal_id', 'fecha', 'metodo_pago'], 'transacciones_caja_idx');
        });

        Schema::table('libro_masters', function (Blueprint $table) {
            $table->index('activo', 'libro_masters_activo_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropUnique('stocks_libro_sucursal_unique');
        });

        Schema::table('precios_libros', function (Blueprint $table) {
            $table->dropIndex('precios_libros_libro_activo_idx');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex('ventas_user_tipo_idx');
            $table->dropIndex('ventas_sucursal_fecha_idx');
        });

        Schema::table('transacciones', function (Blueprint $table) {
            $table->dropIndex('transacciones_caja_idx');
        });

        Schema::table('libro_masters', function (Blueprint $table) {
            $table->dropIndex('libro_masters_activo_idx');
        });
    }
};
