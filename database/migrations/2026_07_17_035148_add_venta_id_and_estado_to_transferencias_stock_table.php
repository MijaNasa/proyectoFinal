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
        Schema::table('transferencias_stock', function (Blueprint $table) {
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->onDelete('set null')->after('id');
            $table->string('estado')->default('completado')->after('cantidad'); // completado for historical ones, pendiente for new automated ones
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
