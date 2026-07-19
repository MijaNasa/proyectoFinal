<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Modificar valores existentes en BD
        DB::table("ventas")->whereIn("estado", ["pagado", "retirado", "entregado"])->update(["estado" => "finalizado"]);
        
        // 2. Modificar el enum de la columna estado a string
        Schema::table("ventas", function (Blueprint $table) {
            $table->string("estado")->nullable()->change();
        });

        // 3. Eliminar la columna estado_envio
        Schema::table("ventas", function (Blueprint $table) {
            if (Schema::hasColumn("ventas", "estado_envio")) {
                $table->dropColumn("estado_envio");
            }
        });
    }

    public function down(): void
    {
        Schema::table("ventas", function (Blueprint $table) {
            $table->string("estado_envio")->default("no_requiere")->after("estado");
        });
        
        // Revertir a pagado por defecto
        DB::table("ventas")->where("estado", "finalizado")->update(["estado" => "pagado"]);
    }
};
