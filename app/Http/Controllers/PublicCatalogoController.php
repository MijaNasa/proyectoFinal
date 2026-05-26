<?php

namespace App\Http\Controllers;

use App\Models\LibroMaster;
use App\Models\Categoria;
use App\Models\Autor;
use App\Models\Serie;
use App\Models\Editorial;
use App\Models\Idioma;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicCatalogoController extends Controller
{
    public function index(Request $request)
    {
        $query = LibroMaster::query()
            ->with([
                'autor',
                'categoria',
                'libros' => function ($q) {
                    $q->with(['precioActual', 'serie', 'editorial', 'idioma', 'stocks.sucursal']);
                },
            ])
            ->where('activo', true);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                  ->orWhere('titulo_original', 'like', "%{$search}%");
            });
        }

        if ($request->filled('categoria')) {
            $query->where('categoria_id', $request->categoria);
        }

        if ($request->filled('autor')) {
            $query->where('autor_id', $request->autor);
        }

        if ($request->filled('serie')) {
            $query->whereHas('libros', fn($q) => $q->where('serie_id', $request->serie));
        }

        if ($request->filled('editorial')) {
            $query->whereHas('libros', fn($q) => $q->where('editorial_id', $request->editorial));
        }

        if ($request->filled('idioma')) {
            $query->whereHas('libros', fn($q) => $q->where('idioma_id', $request->idioma));
        }

        $libros = $query->latest()->paginate(24)->withQueryString();

        return Inertia::render('Catalogo/Index', [
            'libros'      => $libros,
            'categorias'  => Categoria::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'autores'     => Autor::where('activo', true)->orderBy('apellido')->get(['id', 'nombre', 'apellido']),
            'series'      => Serie::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'editoriales' => Editorial::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'idiomas'     => Idioma::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'filters'     => $request->only(['search', 'categoria', 'autor', 'serie', 'editorial', 'idioma']),
        ]);
    }

    public function show($id)
    {
        $libroMaster = LibroMaster::where('activo', true)->with([
            'autor',
            'categoria',
            'libros' => function ($q) {
                $q->with(['editorial', 'idioma', 'serie', 'precioActual', 'stocks.sucursal']);
            },
        ])->findOrFail($id);

        return Inertia::render('Catalogo/Show', [
            'libroMaster' => $libroMaster,
        ]);
    }
}
