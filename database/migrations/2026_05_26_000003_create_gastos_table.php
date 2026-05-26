<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->string('concepto');
            $table->enum('categoria', ['alquiler', 'servicios', 'sueldos', 'insumos', 'impuestos', 'mantenimiento', 'otros'])->default('otros');
            $table->decimal('monto', 12, 2);
            $table->date('fecha')->index();
            $table->string('metodo_pago', 50)->default('Efectivo');
            $table->string('comprobante', 100)->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // Permiso
        DB::table('permisos')->insertOrIgnore([
            'codigo'     => 'gastos.acceder',
            'nombre'     => 'Registrar Gastos',
            'modulo'     => 'gastos',
            'activo'     => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permisoId = DB::table('permisos')->where('codigo', 'gastos.acceder')->value('id');
        if (!$permisoId) return;

        $cargos = DB::table('cargos')->whereIn('nombre', ['ADMIN', 'GERENTE'])->pluck('id');
        foreach ($cargos as $cargoId) {
            if (!DB::table('cargos_permisos')->where('cargo_id', $cargoId)->where('permiso_id', $permisoId)->exists()) {
                DB::table('cargos_permisos')->insert([
                    'cargo_id'   => $cargoId,
                    'permiso_id' => $permisoId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos');
        $permisoId = DB::table('permisos')->where('codigo', 'gastos.acceder')->value('id');
        if ($permisoId) {
            DB::table('cargos_permisos')->where('permiso_id', $permisoId)->delete();
            DB::table('permisos')->where('id', $permisoId)->delete();
        }
    }
};
