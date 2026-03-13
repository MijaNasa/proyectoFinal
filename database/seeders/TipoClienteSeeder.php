<?php

namespace Database\Seeders;

use App\Models\TipoCliente;
use Illuminate\Database\Seeder;

class TipoClienteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tipos = [
            [
                'codigo' => 'PART',
                'nombre' => 'Particular',
                'descuento_porcentaje' => 0,
                'activo' => true
            ],
            [
                'codigo' => 'ABON',
                'nombre' => 'Socio / Abonado',
                'descuento_porcentaje' => 10,
                'activo' => true
            ],
            [
                'codigo' => 'MAYO',
                'nombre' => 'Mayorista',
                'descuento_porcentaje' => 20,
                'activo' => true
            ],
            [
                'codigo' => 'INST',
                'nombre' => 'Institución / Biblioteca',
                'descuento_porcentaje' => 15,
                'activo' => true
            ],
        ];

        foreach ($tipos as $tipo) {
            TipoCliente::updateOrCreate(
                ['codigo' => $tipo['codigo']],
                $tipo
            );
        }
    }
}
