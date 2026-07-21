<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add proveedor_id to libros and libro_masters
        Schema::table('libro_masters', function (Blueprint $table) {
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
        });

        // 2. Transfer data from editoriales to proveedores
        $editoriales = DB::table('editoriales')->get();

        foreach ($editoriales as $editorial) {
            // Find if a proveedor with the same name already exists
            $proveedorId = DB::table('proveedores')
                ->where('nombre_empresa', $editorial->nombre)
                ->value('id');

            if (!$proveedorId) {
                // Create new proveedor from editorial data
                $proveedorId = DB::table('proveedores')->insertGetId([
                    'nombre_empresa' => $editorial->nombre,
                    'nombre_contacto' => null,
                    'telefono' => $editorial->telefono ?? null,
                    'email' => $editorial->email ?? null,
                    'direccion' => trim(($editorial->calle ?? '') . ' ' . ($editorial->numero ?? '')),
                    'activo' => $editorial->activo,
                    'deuda_actual' => 0,
                    'created_at' => $editorial->created_at,
                    'updated_at' => $editorial->updated_at,
                ]);
            }

            // Update libro_masters linking to this editorial
            DB::table('libro_masters')
                ->where('editorial_id', $editorial->id)
                ->update(['proveedor_id' => $proveedorId]);
        }

        // 3. Drop editorial_id from libro_masters
        Schema::table('libro_masters', function (Blueprint $table) {
            $table->dropForeign(['editorial_id']);
            $table->dropColumn('editorial_id');
        });

        // 4. Drop editoriales table
        Schema::dropIfExists('editoriales');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // To reverse, we would recreate the editoriales table, but since this is a destructive merge,
        // full rollback of data separation isn't purely feasible. We recreate structure at least.
        Schema::create('editoriales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('razon_social')->nullable();
            $table->string('tipo')->nullable();
            $table->foreignId('pais_id')->nullable()->constrained('paises')->nullOnDelete();
            $table->string('calle')->nullable();
            $table->string('numero')->nullable();
            $table->string('piso')->nullable();
            $table->string('departamento')->nullable();
            $table->string('codigo_postal')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('libro_masters', function (Blueprint $table) {
            $table->foreignId('editorial_id')->nullable()->constrained('editoriales')->nullOnDelete();
            $table->dropForeign(['proveedor_id']);
            $table->dropColumn('proveedor_id');
        });
    }
};
