<?php

namespace Database\Seeders;

use App\Models\Pais;
use Illuminate\Database\Seeder;

class PaisSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paises = [
            ['nombre' => 'Argentina', 'codigo' => 'AR'],
            ['nombre' => 'España', 'codigo' => 'ES'],
            ['nombre' => 'Estados Unidos', 'codigo' => 'US'],
            ['nombre' => 'Japón', 'codigo' => 'JP'],
            ['nombre' => 'México', 'codigo' => 'MX'],
            ['nombre' => 'Chile', 'codigo' => 'CL'],
            ['nombre' => 'Uruguay', 'codigo' => 'UY'],
            ['nombre' => 'Brasil', 'codigo' => 'BR'],
            ['nombre' => 'Francia', 'codigo' => 'FR'],
            ['nombre' => 'Italia', 'codigo' => 'IT'],
            ['nombre' => 'Reino Unido', 'codigo' => 'UK'],
            ['nombre' => 'Alemania', 'codigo' => 'DE'],
            ['nombre' => 'Corea del Sur', 'codigo' => 'KR'],
            ['nombre' => 'Canadá', 'codigo' => 'CA'],
            ['nombre' => 'Colombia', 'codigo' => 'CO'],
        ];

        foreach ($paises as $pais) {
            Pais::updateOrCreate(['codigo' => $pais['codigo']], $pais);
        }
    }
}
