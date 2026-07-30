<?php

namespace App\Http\Controllers;

use App\Models\Libro;
use App\Models\PrecioLibro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PrecioController extends Controller
{
    public function bulkUpdate(Request $request)
    {
        if (!$request->user() || !$request->user()->esAdmin()) {
            abort(403, 'Acción no autorizada');
        }

        // 1. Validamos que lleguen los datos obligatorios
        $request->validate([
            'criterio'     => 'required|in:categoria,serie,proveedor_formato,libro_individual',
            'nuevo_precio' => 'required|numeric|min:0',
            'categoria_id' => 'nullable|exists:categorias,id',
            'libro_id'     => 'nullable|exists:libros,id'
        ]);

        $query = \App\Models\Libro::query();

        // 2. Filtramos según lo que eligió el usuario
        if ($request->criterio === 'categoria') {
            $query->whereHas('master', function ($q) use ($request) {
                $q->where('categoria_id', $request->categoria_id);
            });
        } elseif ($request->criterio === 'serie') {
            $query->whereHas('master', function ($q) use ($request) {
                $q->where('titulo', $request->serie);
            });
        } elseif ($request->criterio === 'libro_individual') {
            $query->where('id', $request->libro_id);
        } else {
            $query->whereHas('master.proveedor', function ($q) use ($request) {
                $q->where('nombre_empresa', $request->proveedor);
            });
            if (!empty($request->formato)) {
                $query->whereHas('master', function ($q) use ($request) {
                    $q->where('formato', $request->formato);
                });
            }
        }

        $libros = $query->get();

        // 3. Aplicamos el aumento a todos los productos encontrados
        foreach ($libros as $libro) {
            // Capturamos el precio viejo para no perder el dato del costo original
            $precioViejo = $libro->precios()->where('activo', true)->first();
            $costoActual = $precioViejo ? $precioViejo->precio_compra : 0;

            // Desactivamos el historial viejo
            $libro->precios()->update(['activo' => false]);

            // Creamos el nuevo precio
            $libro->precios()->create([
                'precio_compra' => $costoActual,
                'precio_venta'  => $request->nuevo_precio,
                'motivo'        => 'Aumento masivo',
                'fecha_desde'   => now(),
                'activo'        => true,
            ]);
        }

        return redirect()->back();
    }

    public function getOpcionesMasivas(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!$request->user() || !$request->user()->esAdmin()) {
            abort(403, 'Acción no autorizada');
        }

        $opcionesMasivas = [
            'categorias' => \App\Models\Categoria::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'formatos' => \App\Models\LibroMaster::whereNotNull('formato')->where('formato', '!=', '')->distinct()->pluck('formato'),
            'series' => \App\Models\LibroMaster::orderBy('titulo')->pluck('titulo'),
            'proveedores' => \App\Models\Proveedor::where('activo', true)->orderBy('nombre_empresa')->pluck('nombre_empresa'),
            'proveedoresFormatos' => \App\Models\Proveedor::where('activo', true)
                ->with(['libroMasters' => fn($q) => $q->whereNotNull('formato')->where('formato', '!=', '')])
                ->get()
                ->mapWithKeys(function ($prov) {
                    return [
                        $prov->nombre_empresa => $prov->libroMasters->pluck('formato')->unique()->values()->all()
                    ];
                }),
            'libros' => Libro::whereHas('master')->with('master:id,titulo')->select('id', 'master_id', 'numero_tomo')->get()->map(function($l) {
                return [
                    'id' => $l->id,
                    'titulo' => ($l->master ? $l->master->titulo : 'Sin Producto') . ($l->numero_tomo ? ' - Tomo ' . $l->numero_tomo : '')
                ];
            })->sortBy('titulo')->values()
        ];

        return response()->json($opcionesMasivas);
    }



    public function store(Request $request, Libro $libro): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'precio_venta'  => 'required|numeric|min:0',
            'precio_compra' => 'nullable|numeric|min:0',
            'motivo'        => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request, $libro) {
            // Lock the libro row to serialize concurrent price updates for the same book
            \App\Models\Libro::lockForUpdate()->find($libro->id);

            // Cerrar precio anterior y obtener su costo
            $precioAnterior = $libro->precios()->where('activo', true)->first();
            $costoActual = $request->precio_compra ?? ($precioAnterior ? $precioAnterior->precio_compra : null);

            if ($precioAnterior) {
                $precioAnterior->update([
                    'activo'      => false,
                    'fecha_hasta' => now(),
                ]);
            }

            // Crear nuevo precio
            $libro->precios()->create([
                'precio_venta'  => $request->precio_venta,
                'precio_compra' => $costoActual,
                'motivo'        => 'Aumento proveedor',
                'fecha_desde'   => now(),
                'activo'        => true,
            ]);
        });

        return back()->with('message', 'Precio actualizado correctamente');
    }

    public function historial(Libro $libro): \Illuminate\Http\JsonResponse
    {
        $historial = $libro->precios()
            ->orderByDesc('fecha_desde')
            ->get(['id', 'precio_venta', 'precio_compra', 'motivo', 'fecha_desde', 'fecha_hasta', 'activo']);

        return response()->json($historial);
    }
}
