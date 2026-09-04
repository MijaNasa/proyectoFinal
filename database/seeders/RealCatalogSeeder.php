<?php

namespace Database\Seeders;

use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Idioma;
use App\Models\Libro;
use App\Models\LibroMaster;
use App\Models\PrecioLibro;
use App\Models\Proveedor;
use App\Models\Serie;
use App\Models\Stock;
use App\Models\Sucursal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RealCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Idioma
        $idiomaEsp = Idioma::firstOrCreate(['nombre' => 'Español'], ['codigo' => 'ES', 'activo' => true]);
        $idiomaIng = Idioma::firstOrCreate(['nombre' => 'Inglés'], ['codigo' => 'EN', 'activo' => true]);

        // 2. Categorías
        $catShonen = Categoria::firstOrCreate(['nombre' => 'Manga Shonen'], ['descripcion' => 'Manga de acción, aventuras y superación enfocado en público joven.']);
        $catSeinen = Categoria::firstOrCreate(['nombre' => 'Manga Seinen'], ['descripcion' => 'Manga con temáticas maduras, complejas y dramáticas.']);
        $catComicSuper = Categoria::firstOrCreate(['nombre' => 'Comic Superhéroes'], ['descripcion' => 'Cómics americanos de superhéroes (Marvel, DC).']);
        $catNovelaGrafica = Categoria::firstOrCreate(['nombre' => 'Novela Gráfica'], ['descripcion' => 'Obras autoconclusivas y cómics de colección en edición especial.']);

        // 3. Proveedores / Editoriales reales
        $ivrea = $this->buscarOCrearProveedor(
            'Editorial Ivrea Argentina',
            ['email' => 'ventas@ivrea.com.ar', 'telefono' => '11 4567-8900', 'activo' => true, 'deuda_actual' => 0]
        );

        $panini = $this->buscarOCrearProveedor(
            'Panini Comics Argentina',
            ['email' => 'contacto@panini.com.ar', 'telefono' => '11 5234-9900', 'activo' => true, 'deuda_actual' => 0]
        );

        $ovni = $this->buscarOCrearProveedor(
            'Ovni Press',
            ['email' => 'pedidos@ovnipress.com.ar', 'telefono' => '11 4890-1122', 'activo' => true, 'deuda_actual' => 0]
        );

        $ecc = $this->buscarOCrearProveedor(
            'ECC Ediciones',
            ['email' => 'distribucion@eccediciones.com', 'telefono' => '11 4333-2211', 'activo' => true, 'deuda_actual' => 0]
        );

        $planeta = $this->buscarOCrearProveedor(
            'Planeta Cómic',
            ['email' => 'ventas@planetacomic.com.ar', 'telefono' => '11 4111-0099', 'activo' => true, 'deuda_actual' => 0]
        );

        // 4. Sucursales (Asegurar al menos 2 sucursales)
        $sucursalCentral = Sucursal::where('es_principal', true)->first();
        if (!$sucursalCentral) {
            $ciudadRosario = \App\Models\Ciudad::first();
            $sucursalCentral = Sucursal::create([
                'nombre' => 'Sucursal Central - Rosario',
                'calle' => 'San Martín',
                'numero' => '843',
                'ciudad_id' => $ciudadRosario ? $ciudadRosario->id : 1,
                'telefono' => '0341 424-5566',
                'email' => 'rosario@purocomic.com',
                'es_principal' => true,
                'activo' => true,
            ]);
        }

        $sucursalExpress = Sucursal::where('nombre', 'Sucursal Peatonal Córdoba')->first();
        if (!$sucursalExpress) {
            $sucursalExpress = Sucursal::create([
                'nombre' => 'Sucursal Peatonal Córdoba',
                'calle' => 'Córdoba',
                'numero' => '1140',
                'ciudad_id' => $sucursalCentral->ciudad_id,
                'telefono' => '0341 488-9900',
                'email' => 'peatonal@purocomic.com',
                'es_principal' => false,
                'activo' => true,
            ]);
        }

        $sucursales = [$sucursalCentral, $sucursalExpress];

        // 5. Catálogo de Series y Libros
        $catalog = [
            // CHAINSAW MAN - IVREA
            [
                'serie' => 'Chainsaw Man',
                'autor_nombre' => 'Tatsuki',
                'autor_apellido' => 'Fujimoto',
                'proveedor' => $ivrea,
                'categoria' => $catShonen,
                'formato' => 'Tankoubon con Sobrecubierta',
                'precio_compra' => 5200,
                'precio_venta' => 9500,
                'portada' => 'https://images.jikan.moe/manga/covers/116778.jpg',
                'synopsis' => 'Denji es un joven sin dinero que trabaja cazando demonios junto a su fiel perro demonio Pochita. Tras una traición, renace como el Pibe Motosierra.',
                'tomos' => [
                    ['tomo' => 1, 'isbn' => '9789878190014', 'paginas' => 192, 'año' => 2021],
                    ['tomo' => 2, 'isbn' => '9789878190021', 'paginas' => 192, 'año' => 2021],
                    ['tomo' => 3, 'isbn' => '9789878190038', 'paginas' => 192, 'año' => 2021],
                    ['tomo' => 4, 'isbn' => '9789878190045', 'paginas' => 192, 'año' => 2021],
                    ['tomo' => 5, 'isbn' => '9789878190052', 'paginas' => 192, 'año' => 2022],
                    ['tomo' => 6, 'isbn' => '9789878190069', 'paginas' => 192, 'año' => 2022],
                ]
            ],

            // JUJUTSU KAISEN - PANINI
            [
                'serie' => 'Jujutsu Kaisen',
                'autor_nombre' => 'Gege',
                'autor_apellido' => 'Akutami',
                'proveedor' => $panini,
                'categoria' => $catShonen,
                'formato' => 'Tankoubon con Sobrecubierta',
                'precio_compra' => 5400,
                'precio_venta' => 9900,
                'portada' => 'https://images.jikan.moe/manga/covers/113138.jpg',
                'synopsis' => 'Yuji Itadori traga un amuleto maldito con el dedo de Ryomen Sukuna y se ve arrastrado al peligroso mundo de los Hechiceros de Jujutsu.',
                'tomos' => [
                    ['tomo' => 1, 'isbn' => '9786071311009', 'paginas' => 192, 'año' => 2021],
                    ['tomo' => 2, 'isbn' => '9786071311016', 'paginas' => 192, 'año' => 2021],
                    ['tomo' => 3, 'isbn' => '9786071311023', 'paginas' => 192, 'año' => 2021],
                    ['tomo' => 4, 'isbn' => '9786071311030', 'paginas' => 192, 'año' => 2022],
                    ['tomo' => 5, 'isbn' => '9786071311047', 'paginas' => 192, 'año' => 2022],
                ]
            ],

            // DEMON SLAYER - IVREA
            [
                'serie' => 'Demon Slayer: Kimetsu no Yaiba',
                'autor_nombre' => 'Koyoharu',
                'autor_apellido' => 'Gotouge',
                'proveedor' => $ivrea,
                'categoria' => $catShonen,
                'formato' => 'Tankoubon con Sobrecubierta',
                'precio_compra' => 5200,
                'precio_venta' => 9500,
                'portada' => 'https://images.jikan.moe/manga/covers/96792.jpg',
                'synopsis' => 'Tanjiro Kamado emprende un viaje para salvar a su hermana Nezuko, convertida en demonio, y vengar la muerte de su familia.',
                'tomos' => [
                    ['tomo' => 1, 'isbn' => '9789878191103', 'paginas' => 192, 'año' => 2020],
                    ['tomo' => 2, 'isbn' => '9789878191110', 'paginas' => 192, 'año' => 2020],
                    ['tomo' => 3, 'isbn' => '9789878191127', 'paginas' => 192, 'año' => 2020],
                    ['tomo' => 4, 'isbn' => '9789878191134', 'paginas' => 192, 'año' => 2021],
                ]
            ],

            // BERSERK - PANINI
            [
                'serie' => 'Berserk',
                'autor_nombre' => 'Kentaro',
                'autor_apellido' => 'Miura',
                'proveedor' => $panini,
                'categoria' => $catSeinen,
                'formato' => 'B6 con Sobrecubierta',
                'precio_compra' => 7000,
                'precio_venta' => 12900,
                'portada' => 'https://images.jikan.moe/manga/covers/2.jpg',
                'synopsis' => 'Guts, conocido como el Espadachín Negro, busca venganza contra Griffith en un mundo medieval oscuro lleno de monstruos demoníacos.',
                'tomos' => [
                    ['tomo' => 1, 'isbn' => '9786071300904', 'paginas' => 224, 'año' => 2019],
                    ['tomo' => 2, 'isbn' => '9786071300911', 'paginas' => 240, 'año' => 2019],
                    ['tomo' => 3, 'isbn' => '9786071300928', 'paginas' => 240, 'año' => 2019],
                    ['tomo' => 4, 'isbn' => '9786071300935', 'paginas' => 240, 'año' => 2020],
                ]
            ],

            // SPY X FAMILY - IVREA
            [
                'serie' => 'Spy x Family',
                'autor_nombre' => 'Tatsuya',
                'autor_apellido' => 'Endo',
                'proveedor' => $ivrea,
                'categoria' => $catShonen,
                'formato' => 'Tankoubon con Sobrecubierta',
                'precio_compra' => 5200,
                'precio_venta' => 9500,
                'portada' => 'https://images.jikan.moe/manga/covers/119116.jpg',
                'synopsis' => 'Un espía de élite debe formar una familia de mentiras para cumplir su misión: su esposa es una asesina a sueldo y su hija adoptiva puede leer mentes.',
                'tomos' => [
                    ['tomo' => 1, 'isbn' => '9789878192018', 'paginas' => 210, 'año' => 2021],
                    ['tomo' => 2, 'isbn' => '9789878192025', 'paginas' => 200, 'año' => 2021],
                    ['tomo' => 3, 'isbn' => '9789878192032', 'paginas' => 200, 'año' => 2021],
                ]
            ],

            // SPIDER-MAN - OVNI PRESS
            [
                'serie' => 'Spider-Man: De Vuelta a Casa',
                'autor_nombre' => 'J. Michael',
                'autor_apellido' => 'Straczynski',
                'proveedor' => $ovni,
                'categoria' => $catComicSuper,
                'formato' => 'Rústica TPB',
                'precio_compra' => 11500,
                'precio_venta' => 21000,
                'portada' => 'https://m.media-amazon.com/images/I/81+mH29XfGL._AC_UF1000,1000_QL80_.jpg',
                'synopsis' => 'Peter Parker conoce a Ezekiel, un misterioso hombre que posee poderes arácnidos idénticos a los suyos y le revela el origen totémico de sus dones.',
                'tomos' => [
                    ['tomo' => 1, 'isbn' => '9789877245012', 'paginas' => 176, 'año' => 2022],
                ]
            ],

            // BATMAN THE DARK KNIGHT RETURNS - OVNI PRESS
            [
                'serie' => 'Batman: El Caballero de la Noche Regresa',
                'autor_nombre' => 'Frank',
                'autor_apellido' => 'Miller',
                'proveedor' => $ovni,
                'categoria' => $catComicSuper,
                'formato' => 'Tapa Dura Deluxe',
                'precio_compra' => 15500,
                'precio_venta' => 28000,
                'portada' => 'https://m.media-amazon.com/images/I/81xUQo0yQBL._AC_UF1000,1000_QL80_.jpg',
                'synopsis' => 'Un Bruce Wayne de 55 años regresa del retiro para combatir el crimen descontrolado en Ciudad Gótica en un futuro distópico.',
                'tomos' => [
                    ['tomo' => 1, 'isbn' => '9789877249904', 'paginas' => 224, 'año' => 2023],
                ]
            ],

            // WATCHMEN - ECC / PLANETA
            [
                'serie' => 'Watchmen',
                'autor_nombre' => 'Alan',
                'autor_apellido' => 'Moore',
                'proveedor' => $ecc,
                'categoria' => $catNovelaGrafica,
                'formato' => 'Tapa Dura Deluxe',
                'precio_compra' => 18000,
                'precio_venta' => 32000,
                'portada' => 'https://m.media-amazon.com/images/I/71Yn1xO21qL._AC_UF1000,1000_QL80_.jpg',
                'synopsis' => 'En una realidad alternativa en 1985, un grupo de superhéroes retirados investiga el asesinato de uno de los suyos y descubre una conspiración global.',
                'tomos' => [
                    ['tomo' => 1, 'isbn' => '9788419279010', 'paginas' => 416, 'año' => 2023],
                ]
            ],
        ];

        foreach ($catalog as $item) {
            // Autor
            $autor = Autor::firstOrCreate(
                ['nombre' => $item['autor_nombre'], 'apellido' => $item['autor_apellido']],
                ['biografia' => 'Autor destacado reconocido por sus obras de renombre internacional.']
            );

            // Serie
            $serie = Serie::firstOrCreate(
                ['nombre' => $item['serie']],
                ['descripcion' => 'Serie oficial de ' . $item['serie']]
            );

            // Master: una sola obra por serie, con todos sus tomos adentro
            $master = LibroMaster::firstOrCreate(
                ['titulo' => $item['serie']],
                [
                    'autor_id' => $autor->id,
                    'categoria_id' => $item['categoria']->id,
                    'proveedor_id' => $item['proveedor']->id,
                    'idioma_id' => $idiomaEsp->id,
                    'formato' => $item['formato'],
                    'synopsis' => $item['synopsis'],
                    'portada' => $item['portada'],
                    'activo' => true,
                ]
            );

            foreach ($item['tomos'] as $tomoData) {
                // Libro (Edición)
                $libro = Libro::firstOrCreate(
                    [
                        'master_id' => $master->id,
                        'serie_id' => $serie->id,
                        'numero_tomo' => $tomoData['tomo'],
                    ],
                    [
                        'isbn' => $tomoData['isbn'],
                        'año_edicion' => $tomoData['año'],
                        'cantidad_paginas' => $tomoData['paginas'],
                        'activo' => true,
                        'permite_preventa' => false,
                    ]
                );

                // Precio (si no existe)
                if (!$libro->precios()->where('activo', true)->exists()) {
                    PrecioLibro::create([
                        'libro_id' => $libro->id,
                        'precio_compra' => $item['precio_compra'],
                        'precio_venta' => $item['precio_venta'],
                        'motivo' => 'Lista oficial ' . $item['proveedor']->nombre_empresa,
                        'fecha_desde' => now(),
                        'activo' => true,
                    ]);
                }

                // Stock en sucursales
                foreach ($sucursales as $index => $sucursal) {
                    $cant = rand(8, 25) - ($index * 3);
                    Stock::firstOrCreate(
                        [
                            'libro_id' => $libro->id,
                            'sucursal_id' => $sucursal->id,
                        ],
                        [
                            'cantidad_disponible' => max($cant, 5),
                            'cantidad_reservada' => 0,
                            'activo' => true,
                        ]
                    );
                }
            }
        }
    }

    /**
     * nombre_empresa tiene un mutator que lo normaliza a Str::title(strtolower(...))
     * al guardarlo, asi que un firstOrCreate con "ECC Ediciones" nunca vuelve a
     * matchear lo que quedo guardado ("Ecc Ediciones") y se crea de nuevo en cada
     * corrida del seeder. Se busca ignorando mayusculas/minusculas para evitarlo.
     */
    private function buscarOCrearProveedor(string $nombreEmpresa, array $datos): Proveedor
    {
        $proveedor = Proveedor::whereRaw('LOWER(nombre_empresa) = ?', [mb_strtolower($nombreEmpresa, 'UTF-8')])->first();

        if (!$proveedor) {
            $proveedor = Proveedor::create(array_merge(['nombre_empresa' => $nombreEmpresa], $datos));
        }

        return $proveedor;
    }
}
