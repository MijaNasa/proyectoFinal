<?php

namespace Database\Seeders;

use App\Models\Proveedor;
use Illuminate\Database\Seeder;

class ProveedorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $proveedores = [
            ['nombre_empresa' => 'Penguin Random House',      'email' => 'contacto@penguinrandomhouse.com'],
            ['nombre_empresa' => 'Planeta',                   'email' => 'info@planeta.com'],
            ['nombre_empresa' => 'Anagrama',                  'email' => 'anagrama@anagrama-ed.es'],
            ['nombre_empresa' => 'Alfaguara',                 'email' => 'contacto@alfaguara.com'],
            ['nombre_empresa' => 'Tusquets',                  'email' => 'info@tusquetseditores.com'],
            ['nombre_empresa' => 'Seix Barral',                'email' => 'contacto@seixbarral.com'],
            ['nombre_empresa' => 'Sudamericana',               'email' => 'info@sudamericana.com'],
            ['nombre_empresa' => 'Emecé',                     'email' => 'contacto@emece.com'],
            ['nombre_empresa' => 'Siglo XXI',                  'email' => 'ventas@sigloxxieditores.com'],
            ['nombre_empresa' => 'Fondo de Cultura Económica', 'email' => 'contacto@fondodeculturaeconomica.com'],
            ['nombre_empresa' => 'Cátedra',                   'email' => 'info@catedra.com'],
            ['nombre_empresa' => 'Espasa',                     'email' => 'contacto@espasa.es'],
            ['nombre_empresa' => 'Salamandra',                 'email' => 'info@salamandra.info'],
            ['nombre_empresa' => 'Minotauro',                  'email' => 'contacto@minotauro.com'],
            ['nombre_empresa' => 'Debolsillo',                 'email' => 'info@debolsillo.com'],
            ['nombre_empresa' => 'Ediciones B',                'email' => 'contacto@edicionesb.com'],
            ['nombre_empresa' => 'Gredos',                     'email' => 'info@editorialgredos.com'],
            ['nombre_empresa' => 'Acantilado',                 'email' => 'contacto@acantilado.es'],
            ['nombre_empresa' => 'Losada',                     'email' => 'info@editoriallosada.com'],
            ['nombre_empresa' => 'Sigueme',                    'email' => 'contacto@sigueme.es'],
        ];

        foreach ($proveedores as $proveedor) {
            Proveedor::firstOrCreate(
                ['nombre_empresa' => $proveedor['nombre_empresa']],
                [
                    'email'  => $proveedor['email'],
                    'activo' => true,
                    'deuda_actual' => 0,
                ]
            );
        }
    }
}