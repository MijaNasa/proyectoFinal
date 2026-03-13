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
        Schema::create('libro_masters', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('titulo_original')->nullable();
            $table->foreignId('autor_id')->constrained('autores');
            $table->foreignId('categoria_id')->constrained('categorias');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('libros', function (Blueprint $table) {
            $table->id();
            $table->string('isbn', 20)->unique()->nullable();
            $table->foreignId('master_id')->constrained('libro_masters')->onDelete('cascade');
            $table->foreignId('editorial_id')->constrained('editoriales');
            $table->foreignId('idioma_id')->constrained('idiomas');
            $table->integer('año_edicion')->nullable();
            $table->integer('cantidad_paginas')->nullable();
            $table->text('synopsis')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('precios_libros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('libro_id')->constrained('libros')->onDelete('cascade');
            $table->decimal('precio_compra', 12, 2)->nullable();
            $table->decimal('precio_venta', 12, 2);
            $table->timestamp('fecha_desde')->useCurrent();
            $table->timestamp('fecha_hasta')->nullable();
            $table->text('motivo')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('precios_libros');
        Schema::dropIfExists('libros');
        Schema::dropIfExists('libro_masters');
    }
};
