<?php

namespace Database\Seeders;

use App\Models\Editorial;
use Illuminate\Database\Seeder;

class EditorialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $editoriales = [
            ['nombre' => 'Penguin Random House',      'email' => 'contacto@penguinrandomhouse.com'],
            ['nombre' => 'Planeta',                   'email' => 'info@planeta.com'],
            ['nombre' => 'Anagrama',                  'email' => 'anagrama@anagrama-ed.es'],
            ['nombre' => 'Alfaguara',                 'email' => 'contacto@alfaguara.com'],
            ['nombre' => 'Tusquets',                  'email' => 'info@tusquetseditores.com'],
            ['nombre' => 'Seix Barral',                'email' => 'contacto@seixbarral.com'],
            ['nombre' => 'Sudamericana',               'email' => 'info@sudamericana.com'],
            ['nombre' => 'Emecé',                     'email' => 'contacto@emece.com'],
            ['nombre' => 'Siglo XXI',                  'email' => 'ventas@sigloxxieditores.com'],
            ['nombre' => 'Fondo de Cultura Económica', 'email' => 'contacto@fondodeculturaeconomica.com'],
            ['nombre' => 'Cátedra',                   'email' => 'info@catedra.com'],
            ['nombre' => 'Espasa',                     'email' => 'contacto@espasa.es'],
            ['nombre' => 'Salamandra',                 'email' => 'info@salamandra.info'],
            ['nombre' => 'Minotauro',                  'email' => 'contacto@minotauro.com'],
            ['nombre' => 'Debolsillo',                 'email' => 'info@debolsillo.com'],
            ['nombre' => 'Ediciones B',                'email' => 'contacto@edicionesb.com'],
            ['nombre' => 'Gredos',                     'email' => 'info@editorialgredos.com'],
            ['nombre' => 'Acantilado',                 'email' => 'contacto@acantilado.es'],
            ['nombre' => 'Losada',                     'email' => 'info@editoriallosada.com'],
            ['nombre' => 'Sigueme',                    'email' => 'contacto@sigueme.es'],
        ];

        foreach ($editoriales as $editorial) {
            Editorial::firstOrCreate(
                ['nombre' => $editorial['nombre']],
                [
                    'email'  => $editorial['email'],
                    'activo' => true,
                ]
            );
        }
    }
}