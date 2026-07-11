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
            if (!Schema::hasColumn('ventas', 'motivo_pendiente')) {
                $table->string('motivo_pendiente')->nullable()->after('estado');
            }
            if (!Schema::hasColumn('ventas', 'origen')) {
                $table->string('origen')->default('presencial')->after('tipo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'motivo_pendiente')) {
                $table->dropColumn('motivo_pendiente');
            }
            if (Schema::hasColumn('ventas', 'origen')) {
                $table->dropColumn('origen');
            }
        });
    }
};
