<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->string('condicion_pago')->default('cuenta_corriente')->after('estado');
            $table->string('metodo_pago')->nullable()->after('condicion_pago');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropColumn(['condicion_pago', 'metodo_pago']);
        });
    }
};
