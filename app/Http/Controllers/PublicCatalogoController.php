<?php

namespace App\Http\Controllers;

use App\Models\LibroMaster;
use App\Models\Libro;
use App\Models\Categoria;
use App\Models\Autor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicCatalogoController extends Controller
{
    public function index(Request $request)
    {
        $libros = LibroMaster::query()
            ->with(['autor', 'categoria', 'libros.precioActual'])
            ->where('activo', true)
            ->latest()
            ->get();

        return Inertia::render('Catalogo/Index', [
            'libros' => $libros,
            'categorias' => Categoria::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'autores' => Autor::where('activo', true)->orderBy('apellido')->get(['id', 'nombre', 'apellido']),
            'filters' => $request->only(['search', 'categoria', 'autor'])
        ]);
    }

    public function show($id)
    {
        $libroMaster = LibroMaster::with([
            'autor', 
            'categoria', 
            'libros' => function($q) {
                $q->with(['editorial', 'idioma', 'precioActual', 'stocks.sucursal']);
            }
        ])->findOrFail($id);

        return Inertia::render('Catalogo/Show', [
            'libroMaster' => $libroMaster
        ]);
    }
}
