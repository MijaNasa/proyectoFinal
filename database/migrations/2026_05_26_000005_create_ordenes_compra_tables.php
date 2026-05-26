<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_compra', function (Blueprint $table) {
            $table->id();
            $table->string('numero_orden')->unique();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->enum('estado', ['borrador', 'confirmada', 'recibida', 'cancelada'])->default('borrador')->index();
            $table->date('fecha');
            $table->date('fecha_entrega_estimada')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ordenes_compra_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('ordenes_compra')->onDelete('cascade');
            $table->foreignId('libro_id')->constrained('libros');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_compra_items');
        Schema::dropIfExists('ordenes_compra');
    }
};
