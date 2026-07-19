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
        $query = \App\Models\LibroMaster::query()
            ->with(['autor:id,nombre,apellido', 'categoria:id,nombre', 'editorial:id,nombre', 'idioma:id,nombre', 'libros.precios', 'libros.stocks']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                  ->orWhereHas('autor', fn($sq) => $sq->where('nombre', 'like', "%{$search}%")
                      ->orWhere('apellido', 'like', "%{$search}%"))
                  ->orWhereHas('libros', fn($sq) => $sq->where('isbn', 'like', "%{$search}%"));
            });
        }

        $obras = $query->orderBy('titulo')->get();

        return inertia('Libros/Index', [
            'obras'       => $obras,
            'autores'     => \App\Models\Autor::orderBy('apellido')->get(['id', 'nombre', 'apellido']),
            'categorias'  => \App\Models\Categoria::orderBy('nombre')->get(['id', 'nombre']),
            'editoriales' => \App\Models\Editorial::orderBy('nombre')->get(['id', 'nombre']),
            'idiomas'     => \App\Models\Idioma::orderBy('nombre')->get(['id', 'nombre']),
            'sucursales'  => \App\Models\Sucursal::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'filters'     => $request->only(['search']),
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
                'precio_compra' => $request->precio_compra ?? 0,
                'precio_venta'  => $request->precio_venta,
                'fecha_desde'   => now(),
                'activo'        => true,
            ]);

            // Al crear, el stock inicial será estrictamente 0 en todas las sucursales.
            $sucursales = \App\Models\Sucursal::where('activo', true)->pluck('id');
            foreach ($sucursales as $sucursalId) {
                \App\Models\Stock::create([
                    'libro_id'            => $libro->id,
                    'sucursal_id'         => $sucursalId,
                    'cantidad_disponible' => 0,
                    'cantidad_reservada'  => 0,
                    'activo'              => true,
                ]);
            }
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

            $currentPrice = $libro->precioActual; // Note: using relationship property
            
            // Retain the existing precio_compra if updating just the sale price
            $newPrecioCompra = $currentPrice ? $currentPrice->precio_compra : ($request->precio_compra ?? 0);

            if (!$currentPrice || 
                (float)$currentPrice->precio_venta != (float)$request->precio_venta) {
                
                // Desactivar precio anterior si existe
                if ($currentPrice) {
                    $currentPrice->update([
                        'activo' => false,
                        'fecha_hasta' => now()
                    ]);
                }

                // Crear nuevo registro de precio
                $libro->precios()->create([
                    'precio_compra' => $newPrecioCompra,
                    'precio_venta' => $request->precio_venta,
                    'fecha_desde' => now(),
                    'activo' => true,
                ]);
            }
        });

        return redirect()->route('libros.index')
            ->with('message', 'Edición de libro actualizada con éxito');
    }
    

    public function deshabilitarPreventas()
    {
        \App\Models\Libro::where('permite_preventa', true)->update(['permite_preventa' => false]);

        return redirect()->back()->with('message', 'Todas las preventas activas han sido deshabilitadas.');
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
