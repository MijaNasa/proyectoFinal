<?php

namespace App\Http\Controllers;

use App\Models\LibroMaster;
use App\Models\Libro;
use App\Models\Categoria;
use App\Models\Autor;
use App\Models\Serie;
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
                'serie',
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

        if ($request->filled('serie')) {
            $query->where('serie_id', $request->serie);
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

        $libros = $query->orderByRaw('(select coalesce(sum(cantidad_disponible), 0) from stocks where stocks.libro_id = libros.id) > 0 desc')
                        ->latest()
                        ->paginate(24)
                        ->withQueryString();

        return Inertia::render('Catalogo/Index', [
            'libros'      => $libros,
            'categorias'  => Categoria::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'autores'     => Autor::where('activo', true)->orderBy('apellido')->get(['id', 'nombre', 'apellido']),
            'series'      => Serie::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
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
            'serie',
            'precioActual',
            'stocks.sucursal'
        ])
        ->whereHas('master', function ($q) {
            $q->where('activo', true);
        })
        ->where('activo', true)
        ->findOrFail($id);

        // Fetch related books (from the same series, or same category if no series)
        $relacionados = collect();
        if ($libro->serie_id) {
            $relacionados = Libro::with(['master.autor', 'serie', 'precioActual', 'stocks'])
                ->where('serie_id', $libro->serie_id)
                ->where('id', '!=', $libro->id)
                ->where('activo', true)
                ->whereHas('master', fn($q) => $q->where('activo', true))
                ->orderBy('numero_tomo')
                ->take(6)
                ->get();
        } else {
            $relacionados = Libro::with(['master.autor', 'serie', 'precioActual', 'stocks'])
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
}
