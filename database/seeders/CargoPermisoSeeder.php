<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Empleado;
use App\Models\Permiso;
use App\Models\User;
use Illuminate\Database\Seeder;

class CargoPermisoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear todos los permisos del sistema
        $definiciones = [
            ['codigo' => 'dashboard.acceder',   'nombre' => 'Ver Dashboard',                   'modulo' => 'dashboard'],
            ['codigo' => 'ventas.acceder',       'nombre' => 'Gestionar Ventas',                'modulo' => 'ventas'],
            ['codigo' => 'caja.acceder',         'nombre' => 'Cierres de Caja',                 'modulo' => 'caja'],
            ['codigo' => 'catalogo.acceder',     'nombre' => 'Gestionar Catálogo Base',         'modulo' => 'catalogo'],
            ['codigo' => 'colecciones.acceder',  'nombre' => 'Gestionar Colecciones',           'modulo' => 'colecciones'],
            ['codigo' => 'stock.acceder',        'nombre' => 'Control de Stock y Sucursales',   'modulo' => 'stock'],
            ['codigo' => 'clientes.acceder',     'nombre' => 'Gestionar Clientes',              'modulo' => 'clientes'],
            ['codigo' => 'empleados.acceder',    'nombre' => 'Gestionar Empleados',             'modulo' => 'empleados'],
            ['codigo' => 'proveedores.acceder',  'nombre' => 'Proveedores y Series',            'modulo' => 'proveedores'],
            ['codigo' => 'cargos.gestionar',     'nombre' => 'Gestionar Cargos y Asignaciones', 'modulo' => 'administracion'],
            ['codigo' => 'permisos.gestionar',   'nombre' => 'Gestionar Permisos del Sistema',  'modulo' => 'administracion'],
        ];

        foreach ($definiciones as $def) {
            Permiso::firstOrCreate(['codigo' => $def['codigo']], array_merge($def, ['activo' => true]));
        }

        // 2. Crear cargos con sus permisos
        $todos = Permiso::pluck('id', 'codigo');

        $admin = Cargo::firstOrCreate(
            ['nombre' => 'ADMIN'],
            ['descripcion' => 'Administrador del sistema con acceso total', 'activo' => true]
        );
        $admin->permisos()->sync($todos->values()->toArray());

        $gerente = Cargo::firstOrCreate(
            ['nombre' => 'GERENTE'],
            ['descripcion' => 'Gerente de sucursal', 'activo' => true]
        );
        $gerente->permisos()->sync(
            $todos->except('permisos.gestionar')->values()->toArray()
        );

        $vendedor = Cargo::firstOrCreate(
            ['nombre' => 'VENDEDOR'],
            ['descripcion' => 'Personal de ventas y atención al cliente', 'activo' => true]
        );
        $vendedor->permisos()->sync(
            $todos->only(['dashboard.acceder', 'ventas.acceder', 'caja.acceder', 'clientes.acceder', 'catalogo.acceder', 'colecciones.acceder'])->values()->toArray()
        );

        $deposito = Cargo::firstOrCreate(
            ['nombre' => 'DEPOSITO'],
            ['descripcion' => 'Personal de depósito y logística', 'activo' => true]
        );
        $deposito->permisos()->sync(
            $todos->only(['dashboard.acceder', 'stock.acceder', 'proveedores.acceder', 'colecciones.acceder', 'catalogo.acceder'])->values()->toArray()
        );

        // 3. Asignar cargo ADMIN al usuario administrador
        $adminUser = User::where('email', 'admin@purocomic.com')->first();
        if (!$adminUser) return;

        $empleado = $adminUser->empleado;
        if (!$empleado) {
            $sucursal = \App\Models\Sucursal::first();
            $empleado = $adminUser->empleado()->create([
                'legajo'        => 'ADM-001',
                'sucursal_id'   => $sucursal?->id,
                'fecha_ingreso' => now()->toDateString(),
            ]);
        }

        if (!$empleado->cargos()->where('cargo_id', $admin->id)->wherePivotNull('fecha_hasta')->exists()) {
            $empleado->cargos()->attach($admin->id, ['fecha_desde' => now()->toDateString()]);
        }
    }
}
