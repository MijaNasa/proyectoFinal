<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Database\Seeders\TipoClienteSeeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PaisSeeder::class);
        $this->call(GeografiaSeeder::class);
        $this->call(TipoClienteSeeder::class);

        DB::transaction(function () {
            $pais = \App\Models\Pais::firstOrCreate(
                ['codigo' => 'AR'],
                ['nombre' => 'Argentina', 'activo' => true]
            );

            $provincia = \App\Models\Provincia::firstOrCreate(
                ['codigo' => 'BA'],
                ['nombre' => 'Buenos Aires', 'pais_id' => $pais->id, 'activo' => true]
            );

            $ciudad = \App\Models\Ciudad::firstOrCreate(
                ['nombre' => 'Buenos Aires', 'provincia_id' => $provincia->id],
                []
            );

            \App\Models\Sucursal::firstOrCreate(
                ['es_deposito_central' => true],
                [
                    'nombre'              => 'Sucursal Central',
                    'ciudad_id'           => $ciudad->id,
                    'activo'              => true,
                ]
            );

            User::firstOrCreate(
                ['email' => 'admin@purocomic.com'],
                [
                    'name'     => 'Admin PuroComic',
                    'password' => bcrypt('password'),
                ]
            );
        });

        $this->call(CargoPermisoSeeder::class);

        // Asignar cargo ADMIN al usuario admin
        $this->promoverAdmin();

        // Unifica libro_masters duplicados por tomo (idempotente, ya normalizado no matchea de nuevo)
        \Illuminate\Support\Facades\Artisan::call('db:normalize-masters');
    }

    private function promoverAdmin(): void
    {
        $adminEmail = env('ADMIN_EMAIL', 'admin@purocomic.com');

        $user = User::where('email', $adminEmail)->first();
        if (!$user) return;

        $cargo = Cargo::where('nombre', 'ADMIN')->first();
        if (!$cargo) return;

        $sucursal = Sucursal::where('es_deposito_central', true)->first()
            ?? Sucursal::where('activo', true)->first();
        if (!$sucursal) return;

        $empleado = $user->empleado ?? $user->empleado()->create([
            'legajo'      => 'ADM-' . $user->id,
            'sucursal_id' => $sucursal->id,
        ]);

        $yaEsAdmin = $empleado->cargos()
            ->where('nombre', 'ADMIN')
            ->whereNull('empleados_cargos.fecha_hasta')
            ->exists();

        if (!$yaEsAdmin) {
            $empleado->cargos()->attach($cargo->id, [
                'fecha_desde' => now(),
                'fecha_hasta' => null,
            ]);
        }
    }
}
