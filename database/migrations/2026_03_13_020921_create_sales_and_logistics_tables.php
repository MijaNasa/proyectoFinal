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
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->timestamp('fecha')->useCurrent()->index();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('user_id')->constrained('users'); // Empleado que vende
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->enum('tipo', ['presencial', 'online'])->default('presencial');
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->onDelete('cascade');
            $table->foreignId('libro_id')->constrained('libros');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });

        Schema::create('rutas_reparto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->date('fecha')->index();
            $table->foreignId('repartidor_id')->nullable()->constrained('empleados');
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('paradas_reparto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruta_reparto_id')->constrained('rutas_reparto')->onDelete('cascade');
            $table->foreignId('venta_id')->constrained('ventas');
            $table->enum('estado', ['pendiente', 'en camino', 'entregada', 'fallida'])->default('pendiente');
            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
            $table->integer('orden')->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paradas_reparto');
        Schema::dropIfExists('rutas_reparto');
        Schema::dropIfExists('venta_detalles');
        Schema::dropIfExists('ventas');
    }
};
