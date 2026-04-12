<?php

namespace App\Http\Controllers;

use App\Models\Libro;
use App\Http\Requests\StoreLibroRequest;
use App\Http\Requests\UpdateLibroRequest;
use Illuminate\Http\Request;

class LibroController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $libros = Libro::query()
            ->with(['master.autor', 'editorial', 'idioma', 'precios'])
            ->latest()
            ->get();

        return inertia('Libros/Index', [
            'libros' => $libros,
            'masters' => \App\Models\LibroMaster::orderBy('titulo')->get(['id', 'titulo']),
            'editoriales' => \App\Models\Editorial::orderBy('nombre')->get(['id', 'nombre']),
            'idiomas' => \App\Models\Idioma::orderBy('nombre')->get(['id', 'nombre']),
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLibroRequest $request)
    {
        \DB::transaction(function() use ($request) {
            $libro = Libro::create($request->validated());

            $libro->precios()->create([
                'precio_compra' => $request->precio_compra,
                'precio_venta' => $request->precio_venta,
                'fecha_desde' => now(),
                'activo' => true,
            ]);
        });

        return redirect()->route('libros.index')
            ->with('message', 'Edición de libro creada con éxito');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLibroRequest $request, Libro $libro)
    {
        \DB::transaction(function() use ($request, $libro) {
            $libro->update($request->validated());

            $currentPrice = $libro->precio_actual;
            
            if (!$currentPrice || 
                (float)$currentPrice->precio_venta != (float)$request->precio_venta || 
                (float)$currentPrice->precio_compra != (float)$request->precio_compra) {
                
                // Desactivar precio anterior si existe
                if ($currentPrice) {
                    $currentPrice->update([
                        'activo' => false,
                        'fecha_hasta' => now()
                    ]);
                }

                // Crear nuevo registro de precio
                $libro->precios()->create([
                    'precio_compra' => $request->precio_compra,
                    'precio_venta' => $request->precio_venta,
                    'fecha_desde' => now(),
                    'activo' => true,
                ]);
            }
        });

        return redirect()->route('libros.index')
            ->with('message', 'Edición de libro actualizada con éxito');
    }
    

    public function destroy(Libro $libro)
    {
        // Verificar si hay stock activo de este libro en cualquier sucursal
        $tieneStock = \App\Models\Stock::where('libro_id', $libro->id)
            ->where('cantidad_disponible', '>', 0)
            ->exists();

        if ($tieneStock) {
            return redirect()->back()->with('error', 'No se puede eliminar este libro porque todavía hay unidades registradas en el inventario.');
        }

        $libro->delete();

        return redirect()->route('libros.index')
            ->with('message', 'Edición de libro eliminada con éxito');
    }
}
