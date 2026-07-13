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
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('movimiento_stock_detalles');
        Schema::dropIfExists('movimientos_stock');
        Schema::dropIfExists('transferencia_stocks');
        Schema::dropIfExists('tipo_movimiento_stocks');

        Schema::enableForeignKeyConstraints();

        Schema::create('movimientos_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('libro_id')->constrained('libros')->cascadeOnDelete();
            $table->enum('tipo', ['ingreso_proveedor', 'transferencia', 'ajuste']);
            $table->integer('cantidad');
            $table->decimal('costo_unitario', 10, 2)->nullable();
            $table->foreignId('sucursal_origen_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('sucursal_destino_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('motivo')->nullable();
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
