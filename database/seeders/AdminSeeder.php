<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
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
                ['codigo' => 'CENTRAL'],
                [
                    'nombre'             => 'Sucursal Central',
                    'ciudad_id'          => $ciudad->id,
                    'es_deposito_central' => true,
                    'activo'             => true,
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
    }
}
