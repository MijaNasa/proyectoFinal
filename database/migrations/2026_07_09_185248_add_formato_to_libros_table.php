<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El formato (Tankobon, B6, A5, etc.) es un atributo de la obra (LibroMaster),
        // no de la edición (Libro): todas las ediciones/tomos de una obra comparten formato.
        Schema::table('libro_masters', function (Blueprint $table) {
            $table->string('formato')->nullable()->after('categoria_id');
        });
    }

    public function down(): void
    {
        Schema::table('libro_masters', function (Blueprint $table) {
            $table->dropColumn('formato');
        });
    }
};
