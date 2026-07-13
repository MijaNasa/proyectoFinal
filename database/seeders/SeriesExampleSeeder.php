<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Serie;
use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Editorial;
use App\Models\Idioma;
use App\Models\LibroMaster;
use App\Models\Libro;
use App\Models\PrecioLibro;
use Illuminate\Support\Str;

class SeriesExampleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Asegurar dependencias básicas
        $categoria = Categoria::firstOrCreate(['nombre' => 'Manga Shonen'], ['descripcion' => 'Manga demografía Shonen']);
        $idioma = Idioma::firstOrCreate(['nombre' => 'Español']);
        $ivrea = Editorial::firstOrCreate(['nombre' => 'Ivrea']);
        $panini = Editorial::firstOrCreate(['nombre' => 'Panini']);

        // 2. Data de las series a cargar
        $seriesData = [
            [
                'autor' => ['nombre' => 'Akira', 'apellido' => 'Toriyama'],
                'serie' => 'Dragon Ball',
                'editorial' => $ivrea,
                'tomos' => 42,
                'formato' => 'Tankoubon',
                'precio' => 8500
            ],
            [
                'autor' => ['nombre' => 'Masashi', 'apellido' => 'Kishimoto'],
                'serie' => 'Naruto',
                'editorial' => $panini,
                'tomos' => 72,
                'formato' => 'Tankoubon',
                'precio' => 9000
            ],
            [
                'autor' => ['nombre' => 'Eiichiro', 'apellido' => 'Oda'],
                'serie' => 'One Piece',
                'editorial' => $ivrea,
                'tomos' => 105, // Vamos a simular solo los primeros 3 para no saturar
                'formato' => 'Tankoubon',
                'precio' => 8500
            ],
            [
                'autor' => ['nombre' => 'Tite', 'apellido' => 'Kubo'],
                'serie' => 'Bleach',
                'editorial' => $panini,
                'tomos' => 74, // Simular 3
                'formato' => 'Tankoubon',
                'precio' => 9000
            ],
            [
                'autor' => ['nombre' => 'Hajime', 'apellido' => 'Isayama'],
                'serie' => 'Attack on Titan',
                'editorial' => $ivrea,
                'tomos' => 34, // Simular 3
                'formato' => 'B6',
                'precio' => 11000
            ],
        ];

        foreach ($seriesData as $data) {
            // Autor
            $autor = Autor::firstOrCreate(
                ['nombre' => $data['autor']['nombre'], 'apellido' => $data['autor']['apellido']],
                ['biografia' => 'Autor de ' . $data['serie']]
            );

            // Serie
            $serie = Serie::firstOrCreate(
                ['nombre' => $data['serie']],
                ['descripcion' => 'Serie manga de ' . $data['serie']]
            );

            // Cargar los primeros 3 tomos de cada serie para ejemplos
            for ($i = 1; $i <= 3; $i++) {
                // LibroMaster
                $master = LibroMaster::firstOrCreate(
                    ['titulo' => $data['serie'] . ' Tomo ' . $i],
                    [
                        'titulo_original' => $data['serie'] . ' Vol ' . $i,
                        'autor_id' => $autor->id,
                        'categoria_id' => $categoria->id,
                        'activo' => true
                    ]
                );

                // Libro (Edición específica)
                $libro = Libro::firstOrCreate(
                    [
                        'master_id' => $master->id,
                        'editorial_id' => $data['editorial']->id,
                        'serie_id' => $serie->id,
                        'numero_tomo' => $i
                    ],
                    [
                        'isbn' => '978-' . rand(1000000000, 9999999999),
                        'idioma_id' => $idioma->id,
                        'año_edicion' => rand(2015, 2024),
                        'cantidad_paginas' => rand(190, 220),
                        'formato' => $data['formato'],
                        'activo' => true
                    ]
                );

                // Precio del libro
                if (!$libro->precios()->where('activo', true)->exists()) {
                    PrecioLibro::create([
                        'libro_id' => $libro->id,
                        'precio_compra' => $data['precio'] * 0.5, // Simular costo al 50%
                        'precio_venta' => $data['precio'],
                        'motivo' => 'Precio inicial de seeder',
                        'fecha_desde' => now(),
                        'activo' => true
                    ]);
                }
            }
        }
    }
}
