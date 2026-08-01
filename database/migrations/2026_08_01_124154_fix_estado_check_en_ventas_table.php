<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La migracion 2026_07_19_043303_simplify_estados_ventas_table cambio "estado"
     * de enum a string libre, con la intencion de sacarle la restriccion rigida
     * (el codigo agrega estados nuevos como esperando_traslado, acumulado,
     * finalizado o en_preventa sin necesidad de tocar la base). Pero en Postgres
     * cambiar el tipo de columna no borra el check constraint que habia creado el
     * enum original, asi que siguio bloqueando cualquier estado que no estuviera
     * en esa lista vieja (rompio con "listo_para_retiro", el primero de los
     * nuevos que se intento usar de verdad).
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ventas DROP CONSTRAINT IF EXISTS ventas_estado_check');
        }
    }

    public function down(): void
    {
        // No-op: la intencion original (migracion de julio) ya era dejar "estado"
        // como string libre, sin constraint.
    }
};