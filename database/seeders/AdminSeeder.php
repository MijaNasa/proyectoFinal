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
        $this->call(EditorialSeeder::class);

        DB::transaction(function () {
            // La geografia real (Argentina/Santa Fe/Rosario/Funes) ya la crea GeografiaSeeder arriba
            $ciudad = \App\Models\Ciudad::whereHas('provincia', fn($q) => $q->where('nombre', 'Santa Fe'))
                ->where('nombre', 'Rosario')
                ->first();

            \App\Models\Sucursal::firstOrCreate(
                ['es_principal' => true],
                [
                    'nombre'      => 'Sucursal Central',
                    'calle'       => 'San Martín',
                    'numero'      => '843',
                    'ciudad_id'   => $ciudad->id,
                    'telefono'    => '1122334455',
                    'email'       => 'sucursal@purocomic.com',
                    'activo'      => true,
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

        $sucursal = Sucursal::first();
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
