<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En PostgreSQL, la columna tipo_envio se creo originalmente como enum('retiro', 'domicilio'),
     * lo cual genero una restriccion CHECK (ventas_tipo_envio_check).
     * Al agregar 'acumulacion', 'correo_nacional' y 'correo_sucursal', cualquier compra
     * con esas opciones falla en PostgreSQL con Check violation.
     */
    public function up(): void
    {
        try {
            DB::statement("ALTER TABLE ventas DROP CONSTRAINT IF EXISTS ventas_tipo_envio_check;");
        } catch (\Throwable $e) {
            // Ignorar en BDs que no sean Postgres
        }

        try {
            Schema::table('ventas', function (Blueprint $table) {
                $table->string('tipo_envio')->nullable()->change();
            });
        } catch (\Throwable $e) {
            // Ignorar si ya era string o driver sqlite
        }
    }

    public function down(): void
    {
        // No requiere revertir
    }
};
