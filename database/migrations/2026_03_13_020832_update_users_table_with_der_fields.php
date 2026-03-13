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
        Schema::table('users', function (Blueprint $table) {
            $table->string('apellido')->nullable()->after('name');
            $table->string('dni')->unique()->nullable()->after('apellido');
            $table->string('telefono')->nullable();
            $table->string('calle')->nullable();
            $table->string('numero')->nullable();
            $table->string('piso')->nullable();
            $table->string('departamento')->nullable();
            $table->string('codigo_postal')->nullable();
            $table->foreignId('ciudad_id')->nullable()->constrained('ciudades');
            $table->date('fecha_nacimiento')->nullable();
            $table->boolean('activo')->default(true);
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['ciudad_id']);
            $table->dropColumn([
                'apellido', 'dni', 'telefono', 'calle', 'numero', 
                'piso', 'departamento', 'codigo_postal', 'ciudad_id', 
                'fecha_nacimiento', 'activo', 'deleted_at'
            ]);
        });
    }
};
