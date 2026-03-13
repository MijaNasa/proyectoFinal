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
        Schema::create('transacciones', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['ingreso', 'egreso'])->index();
            $table->decimal('monto', 12, 2);
            $table->timestamp('fecha')->useCurrent()->index();
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->nullableMorphs('transaccionable'); // Venta, Gasto, PagoProveedor, etc.
            $table->text('descripcion')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users'); // Empleado que registra
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cierres_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->timestamp('fecha_apertura')->useCurrent();
            $table->timestamp('fecha_cierre')->nullable();
            $table->decimal('saldo_inicial', 12, 2)->default(0);
            $table->decimal('saldo_final', 12, 2)->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cierres_caja');
        Schema::dropIfExists('transacciones');
    }
};
