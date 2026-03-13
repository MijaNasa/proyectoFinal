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
        Schema::table('cierres_caja', function (Blueprint $table) {
            $table->date('fecha')->nullable()->after('sucursal_id');
            $table->decimal('monto_esperado', 12, 2)->default(0)->after('user_id');
            $table->decimal('monto_real', 12, 2)->default(0)->after('monto_esperado');
            $table->decimal('diferencia', 12, 2)->default(0)->after('monto_real');
            $table->text('observaciones')->nullable()->after('diferencia');
        });

        Schema::table('transacciones', function (Blueprint $table) {
            $table->string('metodo_pago', 50)->nullable()->after('monto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->dropColumn('metodo_pago');
        });
        
        Schema::table('cierres_caja', function (Blueprint $table) {
            $table->dropColumn(['fecha', 'monto_esperado', 'monto_real', 'diferencia', 'observaciones']);
        });
    }
};
