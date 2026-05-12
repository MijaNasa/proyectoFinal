<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->enum('estado', [
                'pendiente_pago',
                'pagado',
                'en_preparacion',
                'listo_para_retirar',
                'retirado',
                'enviado',
                'entregado',
                'cancelado',
            ])->nullable()->after('tipo');

            $table->enum('tipo_envio', ['retiro', 'domicilio'])->nullable()->after('estado');
            $table->text('direccion_envio')->nullable()->after('tipo_envio');
            $table->timestamp('pago_expira_at')->nullable()->after('direccion_envio');
            $table->string('payment_id')->nullable()->after('pago_expira_at');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['estado', 'tipo_envio', 'direccion_envio', 'pago_expira_at', 'payment_id']);
        });
    }
};
