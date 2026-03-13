<?php

namespace Database\Seeders;

use App\Models\Pais;
use App\Models\Provincia;
use App\Models\Ciudad;
use Illuminate\Database\Seeder;

class GeografiaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Aseguramos que Argentina exista
        $argentina = Pais::where('codigo', 'AR')->first();
        if (!$argentina) {
            $argentina = Pais::create(['nombre' => 'Argentina', 'codigo' => 'AR']);
        }

        // Provincias de Argentina
        $provincias = [
            ['nombre' => 'Santa Fe', 'codigo' => 'SF'],
            ['nombre' => 'Buenos Aires', 'codigo' => 'BA'],
            ['nombre' => 'Córdoba', 'codigo' => 'CB'],
            ['nombre' => 'Entre Ríos', 'codigo' => 'ER'],
            ['nombre' => 'Mendoza', 'codigo' => 'MZ'],
        ];

        foreach ($provincias as $prov) {
            $provincia = Provincia::updateOrCreate(
                ['nombre' => $prov['nombre'], 'pais_id' => $argentina->id],
                ['codigo' => $prov['codigo']]
            );

            // Si es Santa Fe, agregamos Rosario y Funes
            if ($prov['nombre'] === 'Santa Fe') {
                Ciudad::updateOrCreate(
                    ['nombre' => 'Rosario', 'provincia_id' => $provincia->id],
                    ['codigo_postal' => '2000']
                );
                Ciudad::updateOrCreate(
                    ['nombre' => 'Funes', 'provincia_id' => $provincia->id],
                    ['codigo_postal' => '2132']
                );
            }
            
            // Si es Buenos Aires, agregamos CABA por ejemplo
            if ($prov['nombre'] === 'Buenos Aires') {
                Ciudad::updateOrCreate(
                    ['nombre' => 'CABA', 'provincia_id' => $provincia->id],
                    ['codigo_postal' => '1000']
                );
            }
        }
    }
}
