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
                $q->where('activo', true);
            })
            ->where('activo', true);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('isbn', 'like', "%{$search}%")
                  ->orWhereHas('master', function ($q2) use ($search) {
                      $q2->where('titulo', 'like', "%{$search}%")
                         ->orWhere('titulo_original', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('categoria')) {
            $query->whereHas('master', function ($q) use ($request) {
                $q->where('categoria_id', $request->categoria);
            });
        }

        if ($request->filled('autor')) {
            $query->whereHas('master', function ($q) use ($request) {
                $q->where('autor_id', $request->autor);
            });
        }



        if ($request->filled('proveedor')) {
            $query->whereHas('master', function ($q) use ($request) {
                $q->where('proveedor_id', $request->proveedor);
            });
        }

        if ($request->filled('idioma')) {
            $query->whereHas('master', function ($q) use ($request) {
                $q->where('idioma_id', $request->idioma);
            });
        }

        $query->withSum('stocks', 'cantidad_disponible');

        // Check if there are any search filters applied
        $hasFilters = $request->filled('search') || $request->filled('categoria') || 
                      $request->filled('autor') || $request->filled('proveedor') || 
                      $request->filled('idioma');

        $preventas = collect();

        if (!$hasFilters) {
            // Fetch preventas separately
            $preventas = clone $query;
            $preventas = $preventas->where('permite_preventa', true)
                                   ->orderByRaw('(select coalesce(sum(cantidad_disponible), 0) from stocks where stocks.libro_id = libros.id) > 0 desc')
                                   ->latest()
                                   ->get();
                                   
            // Exclude preventas from main catalog
            $query->where('permite_preventa', false);
        }

        $libros = $query->orderByRaw('(select coalesce(sum(cantidad_disponible), 0) from stocks where stocks.libro_id = libros.id) > 0 desc')
                        ->latest()
                        ->paginate(24)
                        ->withQueryString();

        return Inertia::render('Catalogo/Index', [
            'libros'      => $libros,
            'preventas'   => $preventas,
            'categorias'  => Categoria::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'autores'     => Autor::where('activo', true)->orderBy('apellido')->get(['id', 'nombre', 'apellido']),
            'series'      => [], // Removido por desuso
            'proveedores' => Proveedor::where('activo', true)->orderBy('nombre_empresa')->get(['id', 'nombre_empresa']),
            'idiomas'     => Idioma::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'filters'     => $request->only(['search', 'categoria', 'autor', 'serie', 'proveedor', 'idioma']),
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
                $q->where('isbn', 'like', "%{$term}%")
                  ->orWhereHas('master', function ($q2) use ($term) {
                      $q2->where('titulo', 'like', "%{$term}%")
                         ->orWhere('titulo_original', 'like', "%{$term}%");
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
                ];
            });

        return response()->json($libros);
    }
}
