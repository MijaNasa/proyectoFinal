<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El cargo REPARTIDOR se creo (2026_07_19_232745_add_repartidor_cargo) sin
     * ningun permiso asignado, asi que un empleado con ese cargo unicamente no
     * podia acceder a nada del sistema, ni siquiera a sus propias rutas.
     */
    public function up(): void
    {
        $permisos = [
            ['codigo' => 'dashboard.acceder', 'nombre' => 'Ver Dashboard',      'modulo' => 'dashboard'],
            ['codigo' => 'repartos.acceder',  'nombre' => 'Gestionar Repartos', 'modulo' => 'repartos'],
        ];

        foreach ($permisos as $p) {
            DB::table('permisos')->insertOrIgnore(array_merge($p, [
                'activo'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $cargoId = DB::table('cargos')->where('nombre', 'REPARTIDOR')->value('id');
        if (!$cargoId) return;

        $permisoIds = DB::table('permisos')
            ->whereIn('codigo', ['dashboard.acceder', 'repartos.acceder'])
            ->pluck('id');

        foreach ($permisoIds as $permisoId) {
            $existe = DB::table('cargos_permisos')
                ->where('cargo_id', $cargoId)
                ->where('permiso_id', $permisoId)
                ->exists();

            if (!$existe) {
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
        $cargoId = DB::table('cargos')->where('nombre', 'REPARTIDOR')->value('id');
        if ($cargoId) {
            DB::table('cargos_permisos')->where('cargo_id', $cargoId)->delete();
        }
    }
};