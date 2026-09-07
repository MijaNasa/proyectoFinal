<?php

namespace App\Http\Controllers;

use App\Models\LibroMaster;
use App\Models\Libro;
use App\Models\Categoria;
use App\Models\Autor;
use App\Models\Proveedor;
use App\Models\Idioma;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicCatalogoController extends Controller
{
    public function index(Request $request)
    {
        $query = Libro::query()
            ->with([
                'master.autor',
                'master.categoria',
                'master.proveedor',
                'master.idioma',
                'precioActual',
                'stocks.sucursal'
            ])
            ->whereHas('master', function ($q) {
                $q->where('libro_masters.activo', true);
            })
            ->where('libros.activo', true);

        // 1. Búsqueda por texto (título, autor, editorial, isbn)
        if ($request->filled('search')) {
            $like = '%' . mb_strtolower($request->search) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(libros.isbn) LIKE ?', [$like])
                  ->orWhereHas('master', function ($q2) use ($like) {
                      $q2->whereRaw('LOWER(titulo) LIKE ?', [$like])
                         ->orWhereRaw('LOWER(titulo_original) LIKE ?', [$like])
                         ->orWhereHas('proveedor', fn($p) => $p->whereRaw('LOWER(nombre_empresa) LIKE ?', [$like]))
                         ->orWhereHas('autor', fn($a) => $a->whereRaw('LOWER(nombre) LIKE ? OR LOWER(apellido) LIKE ?', [$like, $like]));
                  });
            });
        }

        // 2. Tipo (manga / comic)
        if ($request->filled('tipo')) {
            $tipo = mb_strtolower($request->tipo);
            $query->whereHas('master.categoria', function ($q) use ($tipo) {
                if ($tipo === 'manga') {
                    $q->whereRaw('LOWER(nombre) LIKE ?', ['%manga%']);
                } elseif ($tipo === 'comic') {
                    $q->whereRaw('LOWER(nombre) NOT LIKE ?', ['%manga%']);
                }
            });
        }

        // ── Base para el cálculo de facetas (conteo por editorial y categoría) ──
        $facetBase = clone $query;

        $proveedoresFiltro = (clone $facetBase)
            ->join('libro_masters', 'libros.master_id', '=', 'libro_masters.id')
            ->join('proveedores', 'libro_masters.proveedor_id', '=', 'proveedores.id')
            ->where('proveedores.activo', true)
            ->where('libro_masters.activo', true)
            ->groupBy('proveedores.id', 'proveedores.nombre_empresa')
            ->selectRaw('proveedores.id, proveedores.nombre_empresa as nombre, count(distinct libros.id) as count')
            ->orderByDesc('count')
            ->get();

        $categoriasFiltro = (clone $facetBase)
            ->join('libro_masters', 'libros.master_id', '=', 'libro_masters.id')
            ->join('categorias', 'libro_masters.categoria_id', '=', 'categorias.id')
            ->where('categorias.activo', true)
            ->where('libro_masters.activo', true)
            ->groupBy('categorias.id', 'categorias.nombre')
            ->selectRaw('categorias.id, categorias.nombre, count(distinct libros.id) as count')
            ->orderByDesc('count')
            ->get();

        // 3. Filtro por Editorial (Proveedor) - Soporta múltiples seleccionados
        $proveedorIds = [];
        if ($request->filled('proveedor')) {
            $rawProv = $request->input('proveedor');
            $proveedorIds = is_array($rawProv) ? $rawProv : explode(',', (string)$rawProv);
            $proveedorIds = array_values(array_filter(array_map('intval', $proveedorIds)));
            if (!empty($proveedorIds)) {
                $query->whereHas('master', function ($q) use ($proveedorIds) {
                    $q->whereIn('proveedor_id', $proveedorIds);
                });
            }
        }

        // 4. Filtro por Categoría - Soporta múltiples seleccionadas
        $categoriaIds = [];
        if ($request->filled('categoria')) {
            $rawCat = $request->input('categoria');
            $categoriaIds = is_array($rawCat) ? $rawCat : explode(',', (string)$rawCat);
            $categoriaIds = array_values(array_filter(array_map('intval', $categoriaIds)));
            if (!empty($categoriaIds)) {
                $query->whereHas('master', function ($q) use ($categoriaIds) {
                    $q->whereIn('categoria_id', $categoriaIds);
                });
            }
        }

        if ($request->filled('autor')) {
            $query->whereHas('master', function ($q) use ($request) {
                $q->where('autor_id', $request->autor);
            });
        }

        if ($request->filled('idioma')) {
            $query->whereHas('master', function ($q) use ($request) {
                $q->where('idioma_id', $request->idioma);
            });
        }

        // 5. Rango de precio (min y max)
        if ($request->filled('precio_min')) {
            $min = (float) $request->precio_min;
            $query->whereRaw('COALESCE((SELECT precio_venta FROM precios_libros WHERE precios_libros.libro_id = libros.id AND precios_libros.activo = true ORDER BY fecha_desde DESC LIMIT 1), 0) >= ?', [$min]);
        }

        if ($request->filled('precio_max')) {
            $max = (float) $request->precio_max;
            $query->whereRaw('COALESCE((SELECT precio_venta FROM precios_libros WHERE precios_libros.libro_id = libros.id AND precios_libros.activo = true ORDER BY fecha_desde DESC LIMIT 1), 0) <= ?', [$max]);
        }

        // 6. Disponibilidad: Solo en stock
        if ($request->boolean('solo_stock')) {
            $query->whereHas('stocks', function ($q) {
                $q->where('cantidad_disponible', '>', 0);
            });
        }

        // 7. Disponibilidad: Preventas
        if ($request->boolean('preventa')) {
            $query->where('libros.permite_preventa', true);
        }

        $query->withSum('stocks', 'cantidad_disponible');

        // Check if there are any search filters applied
        $hasFilters = $request->filled('search') || !empty($categoriaIds) || 
                      $request->filled('autor') || !empty($proveedorIds) || 
                      $request->filled('idioma') || $request->filled('tipo') ||
                      $request->boolean('preventa') || $request->filled('precio_min') ||
                      $request->filled('precio_max') || $request->boolean('solo_stock') ||
                      ($request->filled('orden') && $request->orden !== 'relevancia');

        $preventas = collect();

        if (!$hasFilters) {
            // Fetch preventas separately for the top carousel
            $preventas = (clone $query)
                ->where('libros.permite_preventa', true)
                ->orderByRaw('(select coalesce(sum(cantidad_disponible), 0) from stocks where stocks.libro_id = libros.id) > 0 desc')
                ->latest('libros.id')
                ->get();
                                       
            // Exclude preventas from main catalog when viewing initial page
            $query->where('libros.permite_preventa', false);
        }

        // 8. Ordenamiento
        $orden = $request->input('orden', 'relevancia');
        switch ($orden) {
            case 'precio_asc':
                $query->orderByRaw('COALESCE((SELECT precio_venta FROM precios_libros WHERE precios_libros.libro_id = libros.id AND precios_libros.activo = true ORDER BY fecha_desde DESC LIMIT 1), 99999999) ASC');
                break;
            case 'precio_desc':
                $query->orderByRaw('COALESCE((SELECT precio_venta FROM precios_libros WHERE precios_libros.libro_id = libros.id AND precios_libros.activo = true ORDER BY fecha_desde DESC LIMIT 1), 0) DESC');
                break;
            case 'recientes':
                $query->latest('libros.created_at');
                break;
            case 'nombre_asc':
                $query->join('libro_masters', 'libros.master_id', '=', 'libro_masters.id')
                      ->select('libros.*')
                      ->orderBy('libro_masters.titulo', 'ASC')
                      ->orderBy('libros.numero_tomo', 'ASC');
                break;
            case 'relevancia':
            default:
                $query->orderByRaw('(select coalesce(sum(cantidad_disponible), 0) from stocks where stocks.libro_id = libros.id) > 0 desc')
                      ->latest('libros.id');
                break;
        }

        $libros = $query->paginate(24)->withQueryString();

        // Rango de precio sugerido
        $precioMinMax = \Illuminate\Support\Facades\DB::table('precios_libros')
            ->where('activo', true)
            ->selectRaw('MIN(precio_venta) as min_p, MAX(precio_venta) as max_p')
            ->first();
        $minSugerido = $precioMinMax?->min_p ? (int) floor($precioMinMax->min_p) : 1000;
        $maxSugerido = $precioMinMax?->max_p ? (int) ceil($precioMinMax->max_p) : 50000;

        return Inertia::render('Catalogo/Index', [
            'libros'            => $libros,
            'preventas'         => $preventas,
            'proveedoresFiltro' => $proveedoresFiltro,
            'categoriasFiltro'  => $categoriasFiltro,
            'precioRango'       => [
                'min' => $minSugerido,
                'max' => $maxSugerido,
            ],
            'categorias'        => fn() => Categoria::where('activo', true)->whereHas('libroMasters', fn($q) => $q->where('activo', true))->orderBy('nombre')->get(['id', 'nombre']),
            'autores'           => fn() => Autor::where('activo', true)->whereHas('libroMasters', fn($q) => $q->where('activo', true))->orderBy('apellido')->get(['id', 'nombre', 'apellido']),
            'series'            => [],
            'proveedores'       => fn() => Proveedor::where('activo', true)->whereHas('libroMasters', fn($q) => $q->where('activo', true))->orderBy('nombre_empresa')->get(['id', 'nombre_empresa']),
            'idiomas'           => fn() => Idioma::where('activo', true)->whereHas('libroMasters', fn($q) => $q->where('activo', true))->orderBy('nombre')->get(['id', 'nombre']),
            'filters'           => [
                'search'     => $request->input('search'),
                'categoria'  => $categoriaIds,
                'proveedor'  => $proveedorIds,
                'autor'      => $request->input('autor'),
                'idioma'     => $request->input('idioma'),
                'tipo'       => $request->input('tipo'),
                'preventa'   => $request->boolean('preventa'),
                'solo_stock' => $request->boolean('solo_stock'),
                'precio_min' => $request->input('precio_min'),
                'precio_max' => $request->input('precio_max'),
                'orden'      => $orden,
            ],
        ]);
    }

    public function show($id)
    {
        $libro = Libro::with([
            'master.autor',
            'master.categoria',
            'master.proveedor',
            'master.idioma',
            'precioActual',
            'stocks.sucursal'
        ])
        ->whereHas('master', function ($q) {
            $q->where('activo', true);
        })
        ->where('activo', true)
        ->findOrFail($id);

        // Fetch related books (from the same master, or same category if no master)
        $relacionados = collect();
        if ($libro->master_id) {
            $relacionados = Libro::with(['master.autor', 'precioActual', 'stocks'])
                ->where('master_id', $libro->master_id)
                ->where('id', '!=', $libro->id)
                ->where('activo', true)
                ->whereHas('master', fn($q) => $q->where('activo', true))
                ->take(6)
                ->get();
        } else {
            $relacionados = Libro::with(['master.autor', 'precioActual', 'stocks'])
                ->whereHas('master', function ($q) use ($libro) {
                    $q->where('categoria_id', $libro->master->categoria_id)
                      ->where('activo', true);
                })
                ->where('id', '!=', $libro->id)
                ->where('activo', true)
                ->inRandomOrder()
                ->take(6)
                ->get();
        }

        return Inertia::render('Catalogo/Show', [
            'libro' => $libro,
            'relacionados' => $relacionados,
        ]);
    }

    public function search(Request $request)
    {
        $term = trim($request->input('q', ''));
        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $libros = Libro::query()
            ->with(['master', 'precioActual'])
            ->whereHas('master', fn($q) => $q->where('activo', true))
            ->where('activo', true)
            ->where(function ($q) use ($term) {
                $like = '%' . mb_strtolower($term) . '%';
                $q->whereRaw('LOWER(isbn) LIKE ?', [$like])
                  ->orWhereHas('master', function ($q2) use ($like) {
                      $q2->whereRaw('LOWER(titulo) LIKE ?', [$like])
                         ->orWhereRaw('LOWER(titulo_original) LIKE ?', [$like]);
                  });
            })
            ->limit(8)
            ->get()
            ->map(function ($libro) {
                $precio = $libro->precioActual?->precio_venta;
                if ($libro->permite_preventa && $precio) {
                    $precio = $precio * 0.90;
                }
                return [
                    'id' => $libro->id,
                    'titulo' => ($libro->master?->titulo ?? '') . ($libro->numero_tomo ? ' - Tomo ' . $libro->numero_tomo : ''),
                    'portada_url' => $libro->portada_url,
                    'precio' => $precio ? '$' . number_format($precio, 2, ',', '.') : 'Consultar',
                    'precio_original' => ($libro->permite_preventa && $libro->precioActual?->precio_venta) ? '$' . number_format($libro->precioActual->precio_venta, 2, ',', '.') : null,
                    'es_preventa' => (bool) $libro->permite_preventa,
                ];
            });

        return response()->json($libros);
    }
}
