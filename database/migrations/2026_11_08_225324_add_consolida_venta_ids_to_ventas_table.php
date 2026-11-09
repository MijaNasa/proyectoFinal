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
        Schema::table('ventas', function (Blueprint $table) {
            // IDs (separados por coma) de las ventas en Acumulacion que esta
            // venta consolida una vez que SU pago (el costo de envio) se
            // confirme. Null para cualquier venta normal.
            $table->text('consolida_venta_ids')->nullable()->after('costo_envio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('consolida_venta_ids');
        });
    }
};
