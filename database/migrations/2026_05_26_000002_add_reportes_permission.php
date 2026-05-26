<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permisos')->insertOrIgnore([
            'codigo'     => 'reportes.acceder',
            'nombre'     => 'Ver Reportes',
            'modulo'     => 'reportes',
            'activo'     => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permisoId = DB::table('permisos')->where('codigo', 'reportes.acceder')->value('id');
        if (!$permisoId) return;

        $cargos = DB::table('cargos')->whereIn('nombre', ['ADMIN', 'GERENTE'])->pluck('id');

        foreach ($cargos as $cargoId) {
            $exists = DB::table('cargos_permisos')
                ->where('cargo_id', $cargoId)
                ->where('permiso_id', $permisoId)
                ->exists();

            if (!$exists) {
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
        $permisoId = DB::table('permisos')->where('codigo', 'reportes.acceder')->value('id');
        if ($permisoId) {
            DB::table('cargos_permisos')->where('permiso_id', $permisoId)->delete();
            DB::table('permisos')->where('id', $permisoId)->delete();
        }
    }
};
