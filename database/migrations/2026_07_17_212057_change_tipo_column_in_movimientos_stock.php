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
        if (!Schema::hasTable('movimientos_stock')) {
            return;
        }

        Schema::table('movimientos_stock', function (Blueprint $table) {
            $table->string('tipo', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            // Not easily reversible for sqlite enum
        });
    }
};
