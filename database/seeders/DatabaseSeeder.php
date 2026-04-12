<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Datos base (Seeders existentes)
        $this->call([
            PaisSeeder::class,
            GeografiaSeeder::class,
            TipoClienteSeeder::class,
        ]);

        // 2. Usuarios base
        \App\Models\User::factory()->create([
            'name' => 'Admin PuroComic',
            'email' => 'admin@purocomic.com',
            'password' => bcrypt('password'),
        ]);

        // 3. Catálogo y Operaciones (Factories)
        // Creamos sucursales primero
        $sucursales = \App\Models\Sucursal::factory()->count(3)->create();

        // Creamos Categorías e Idiomas
        \App\Models\Categoria::factory()->count(10)->create();
        \App\Models\Idioma::factory()->count(5)->create();

        // Creamos Autores y Editoriales
        $autores = \App\Models\Autor::factory()->count(20)->create();
        $editoriales = \App\Models\Editorial::factory()->count(10)->create();

        // Creamos Libros (Master y Variantes con Stock)
        foreach ($editoriales as $editorial) {
            $libroMasters = \App\Models\LibroMaster::factory()->count(5)->create([
                'autor_id' => $autores->random()->id,
                'categoria_id' => \App\Models\Categoria::inRandomOrder()->first()->id,
            ]);

            foreach ($libroMasters as $master) {
                $libro = \App\Models\Libro::factory()->create([
                    'master_id' => $master->id,
                    'editorial_id' => $editorial->id,
                    'idioma_id' => \App\Models\Idioma::inRandomOrder()->first()->id,
                ]);

                // Crear precio actual
                \App\Models\PrecioLibro::factory()->create(['libro_id' => $libro->id]);

                // Crear stock en cada sucursal
                foreach ($sucursales as $sucursal) {
                    \App\Models\Stock::factory()->create([
                        'libro_id' => $libro->id,
                        'sucursal_id' => $sucursal->id,
                    ]);
                }
            }
        }

        // 4. Clientes y Proveedores
        \App\Models\Cliente::factory()->count(20)->create();
        \App\Models\Proveedor::factory()->count(10)->create();

        // 5. Ventas para el Dashboard
        \App\Models\Venta::factory()->count(50)->create();
    }
}
