<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement("ALTER TABLE movimientos_stock DROP CONSTRAINT IF EXISTS movimientos_stock_tipo_check;");
        } catch (\Throwable $e) {
            // Ignorar en bases de datos que no tengan la restricción de enum de postgresql
        }
    }

    public function down(): void
    {
        // No requiere revertir
    }
};
