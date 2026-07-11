<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Reemplaza el diseño viejo de movimientos de stock (movimientos_stock +
     * tipos_movimientos_stock + transferencias_stock) por el nuevo esquema
     * cabecera/detalle: movimientos_stock (tipo/origen/destino/usuario/motivo)
     * + movimiento_stock_detalles (libro/cantidad/costo_unitario).
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('movimientos_stock');
        Schema::dropIfExists('transferencias_stock');
        Schema::dropIfExists('tipos_movimientos_stock');

        Schema::enableForeignKeyConstraints();

        // Master (Cabecera)
        Schema::create('movimientos_stock', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['ingreso_proveedor', 'transferencia', 'ajuste']);
            $table->foreignId('sucursal_origen_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('sucursal_destino_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo')->nullable();
            $table->timestamps();
        });

        // Detalles (Líneas)
        Schema::create('movimiento_stock_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movimiento_id')->constrained('movimientos_stock')->cascadeOnDelete();
            $table->foreignId('libro_id')->constrained('libros')->cascadeOnDelete();
            $table->integer('cantidad');
            $table->decimal('costo_unitario', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimiento_stock_detalles');
        Schema::dropIfExists('movimientos_stock');
    }
};
