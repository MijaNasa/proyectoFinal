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
            ->with(['autor:id,nombre,apellido', 'categoria:id,nombre', 'proveedor:id,nombre_empresa', 'idioma:id,nombre', 'libros.precios', 'libros.stocks']);

        if ($request->filled('search')) {
            $like = '%' . mb_strtolower($request->search) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(titulo) LIKE ?', [$like])
                  ->orWhereHas('autor', fn($sq) => $sq->whereRaw('LOWER(nombre) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(apellido) LIKE ?', [$like]))
                  ->orWhereHas('libros', fn($sq) => $sq->whereRaw('LOWER(isbn) LIKE ?', [$like]));
            });
        }

        $obras = $query->orderBy('titulo')->get();

        return inertia('Libros/Index', [
            'obras'       => $obras,
            'autores'     => \App\Models\Autor::orderBy('apellido')->get(['id', 'nombre', 'apellido']),
            'categorias'  => \App\Models\Categoria::orderBy('nombre')->get(['id', 'nombre']),
            'proveedores' => \App\Models\Proveedor::where('activo', true)->orderBy('nombre_empresa')->get(['id', 'nombre_empresa']),
            'idiomas'     => \App\Models\Idioma::orderBy('nombre')->get(['id', 'nombre']),
            'formatos'    => \App\Models\Formato::where('activo', true)->orderBy('nombre')->pluck('nombre'),
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
            $data = $request->validated();
            if (empty($data['isbn']) || trim($data['isbn']) === '') {
                $data['isbn'] = null;
            }
            if (empty($data['numero_tomo']) || trim($data['numero_tomo']) === '') {
                $data['numero_tomo'] = null;
            }

            if ($request->hasFile('portada')) {
                $data['portada'] = $request->file('portada')->store('portadas', 'public');
            }

            $libro = Libro::create($data);

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
            $data = $request->validated();
            unset($data['activo']);

            if (empty($data['isbn']) || trim($data['isbn']) === '') {
                $data['isbn'] = null;
            }
            if (empty($data['numero_tomo']) || trim($data['numero_tomo']) === '') {
                $data['numero_tomo'] = null;
            }

            if ($request->hasFile('portada')) {
                if ($libro->portada && \Illuminate\Support\Facades\Storage::disk('public')->exists($libro->portada)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($libro->portada);
                }
                $data['portada'] = $request->file('portada')->store('portadas', 'public');
            } elseif ($request->boolean('quitar_portada')) {
                if ($libro->portada && \Illuminate\Support\Facades\Storage::disk('public')->exists($libro->portada)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($libro->portada);
                }
                $data['portada'] = null;
            } else {
                unset($data['portada']);
            }

            $libro->update($data);

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
    
    /**
     * Alternar estado activo / inactivo de un tomo individual con validación de stock.
     */
    public function toggleActivo(Libro $libro)
    {
        $nuevoEstado = !$libro->activo;

        if (!$nuevoEstado) {
            $totalStock = (int) $libro->stocks()
                ->where(function ($q) {
                    $q->where('cantidad_disponible', '>', 0)
                      ->orWhere('cantidad_reservada', '>', 0);
                })
                ->sum(\DB::raw('cantidad_disponible + cantidad_reservada'));

            if ($totalStock > 0) {
                return redirect()->back()->with('error_modal', "No se puede desactivar este tomo porque todavía posee {$totalStock} unidad(es) de stock en inventario. Debe vaciarse o transferirse el stock antes.");
            }
        }

        $libro->update(['activo' => $nuevoEstado]);

        $mensaje = $nuevoEstado 
            ? 'Ítem/Tomo reactivado con éxito en el catálogo.' 
            : 'Ítem/Tomo desactivado con éxito. Su historial permanece intacto.';

        return redirect()->back()->with('swal_success', $mensaje);
    }

    public function deshabilitarPreventas()
    {
        \App\Models\Libro::where('permite_preventa', true)->update(['permite_preventa' => false]);

        return redirect()->back()->with('message', 'Todas las preventas activas han sido deshabilitadas.');
    }

    public function destroy(Libro $libro)
    {
        return $this->toggleActivo($libro);
    }
}
