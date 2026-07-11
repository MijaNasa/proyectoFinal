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
        Schema::create('suscripcions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained()->onDelete('cascade');
            $table->foreignId('libro_master_id')->constrained()->onDelete('cascade');
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->onDelete('set null');
            $table->enum('estado', ['activa', 'pausada'])->default('activa');
            $table->timestamps();
            
            // Un cliente solo puede estar suscrito una vez a la misma serie en la misma sucursal
            $table->unique(['cliente_id', 'libro_master_id', 'sucursal_id'], 'sub_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suscripcions');
    }
};
