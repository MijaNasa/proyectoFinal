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
        // 1. Add columns to libro_masters
        Schema::table('libro_masters', function (Blueprint $table) {
            $table->foreignId('editorial_id')->nullable()->constrained('editoriales');
            $table->foreignId('idioma_id')->nullable()->constrained('idiomas');
            $table->string('formato')->nullable();
            $table->text('synopsis')->nullable();
        });

        // 2. Migrate data from libros to libro_masters
        $masters = \App\Models\LibroMaster::all();
        foreach ($masters as $master) {
            $primerLibro = \App\Models\Libro::where('master_id', $master->id)->first();
            if ($primerLibro) {
                $master->update([
                    'editorial_id' => $primerLibro->editorial_id,
                    'idioma_id' => $primerLibro->idioma_id,
                    'formato' => $primerLibro->formato,
                    'synopsis' => $primerLibro->synopsis,
                ]);
            }
        }

        // 3. Drop columns from libros
        Schema::table('libros', function (Blueprint $table) {
            $table->dropForeign(['editorial_id']);
            $table->dropForeign(['idioma_id']);
            $table->dropColumn(['editorial_id', 'idioma_id', 'formato', 'synopsis']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Add columns back to libros
        Schema::table('libros', function (Blueprint $table) {
            $table->foreignId('editorial_id')->nullable()->constrained('editoriales');
            $table->foreignId('idioma_id')->nullable()->constrained('idiomas');
            $table->string('formato')->nullable();
            $table->text('synopsis')->nullable();
        });

        // 2. Migrate data back from libro_masters to libros
        $libros = \App\Models\Libro::all();
        foreach ($libros as $libro) {
            $master = \App\Models\LibroMaster::find($libro->master_id);
            if ($master) {
                $libro->update([
                    'editorial_id' => $master->editorial_id,
                    'idioma_id' => $master->idioma_id,
                    'formato' => $master->formato,
                    'synopsis' => $master->synopsis,
                ]);
            }
        }

        // 3. Drop columns from libro_masters
        Schema::table('libro_masters', function (Blueprint $table) {
            $table->dropForeign(['editorial_id']);
            $table->dropForeign(['idioma_id']);
            $table->dropColumn(['editorial_id', 'idioma_id', 'formato', 'synopsis']);
        });
    }
};
