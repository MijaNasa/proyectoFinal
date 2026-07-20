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
        // La tabla puede no existir si se recreo la base sin conservar el historial
        // de datos (ej. expiracion del Postgres free de Render); la recreamos con
        // su definicion original antes de agregarle las columnas nuevas.
        if (!Schema::hasTable('transferencias_stock')) {
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
        }

        Schema::table('transferencias_stock', function (Blueprint $table) {
            if (!Schema::hasColumn('transferencias_stock', 'venta_id')) {
                $table->foreignId('venta_id')->nullable()->constrained('ventas')->onDelete('set null')->after('id');
            }
            if (!Schema::hasColumn('transferencias_stock', 'estado')) {
                $table->string('estado')->default('completado')->after('cantidad'); // completado for historical ones, pendiente for new automated ones
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transferencias_stock', function (Blueprint $table) {
            $table->dropForeign(['venta_id']);
            $table->dropColumn(['venta_id', 'estado']);
        });
    }
};
