<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use Illuminate\Http\Request;

class OrdenCompraController extends Controller
{
    public function index(Request $request)
    {
        $query = OrdenCompra::with([
            'proveedor', 'sucursal', 'user:id,name,apellido',
            'items.libro.master:id,titulo', 'items.libro:id,master_id,isbn,numero_tomo'
        ])->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero_orden', 'like', "%{$search}%")
                  ->orWhereHas('proveedor', fn($q2) => $q2->where('nombre', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $ordenes = $query->paginate(15)->withQueryString();

        $stats = [
            'total'      => OrdenCompra::count(),
            'borradores' => OrdenCompra::where('estado', 'borrador')->count(),
            'confirmadas'=> OrdenCompra::where('estado', 'confirmada')->count(),
            'recibidas'  => OrdenCompra::where('estado', 'recibida')->count(),
        ];

        return inertia('OrdenesCompra/Index', [
            'ordenes'     => $ordenes,
            'proveedores' => \App\Models\Proveedor::where('activo', true)->orderBy('nombre_empresa')->get(['id', 'nombre_empresa as nombre']),
            'sucursales'  => \App\Models\Sucursal::where('activo', true)->get(['id', 'nombre']),
            'stats'       => $stats,
            'filters'     => $request->only(['search', 'estado']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'proveedor_id'           => 'required|exists:proveedores,id',
            'sucursal_id'            => 'required|exists:sucursales,id',
            'observaciones'          => 'nullable|string',
            'items'                  => 'required|array|min:1',
            'items.*.libro_id'       => 'required|exists:libros,id',
            'items.*.cantidad'       => 'required|integer|min:1',
            'items.*.precio_unitario'=> 'required|numeric|min:0',
        ]);

        $total = collect($request->items)->sum(fn($i) => $i['cantidad'] * $i['precio_unitario']);

        $orden = \DB::transaction(function () use ($request, $total) {
            // Crear primero para obtener el ID autoincremental; luego generar el número
            $orden = OrdenCompra::create([
                'numero_orden'           => 'OC-TEMP',
                'proveedor_id'           => $request->proveedor_id,
                'sucursal_id'            => $request->sucursal_id,
                'estado'                 => 'confirmada',
                'fecha'                  => now()->toDateString(),
                'total'                  => $total,
                'observaciones'          => $request->observaciones,
                'user_id'                => \Auth::id(),
            ]);

            $orden->update(['numero_orden' => 'OC-' . str_pad($orden->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($request->items as $item) {
                $orden->items()->create([
                    'libro_id'        => $item['libro_id'],
                    'cantidad'        => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal'        => $item['cantidad'] * $item['precio_unitario'],
                ]);
            }

            return $orden;
        });

        return redirect()->route('ordenes-compra.index')
            ->with('message', "Orden {$orden->numero_orden} creada.");
    }

    public function update(Request $request, OrdenCompra $ordenesCompra)
    {
        if (!in_array($ordenesCompra->estado, ['borrador', 'confirmada'])) {
            return back()->withErrors(['estado' => 'Solo se puede editar una orden en borrador o confirmada.']);
        }

        $request->validate([
            'proveedor_id'           => 'required|exists:proveedores,id',
            'sucursal_id'            => 'required|exists:sucursales,id',
            'observaciones'          => 'nullable|string',
            'items'                  => 'required|array|min:1',
            'items.*.libro_id'       => 'required|exists:libros,id',
            'items.*.cantidad'       => 'required|integer|min:1',
            'items.*.precio_unitario'=> 'required|numeric|min:0',
        ]);

        $total = collect($request->items)->sum(fn($i) => $i['cantidad'] * $i['precio_unitario']);

        \DB::transaction(function () use ($request, $ordenesCompra, $total) {
            $ordenesCompra->update([
                'proveedor_id'           => $request->proveedor_id,
                'sucursal_id'            => $request->sucursal_id,
                'total'                  => $total,
                'observaciones'          => $request->observaciones,
            ]);

            $ordenesCompra->items()->delete();

            foreach ($request->items as $item) {
                $ordenesCompra->items()->create([
                    'libro_id'        => $item['libro_id'],
                    'cantidad'        => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal'        => $item['cantidad'] * $item['precio_unitario'],
                ]);
            }
        });

        return redirect()->route('ordenes-compra.index')
            ->with('message', "Orden {$ordenesCompra->numero_orden} actualizada.");
    }

    public function show(OrdenCompra $ordenesCompra)
    {
        $ordenesCompra->load([
            'proveedor',
            'sucursal',
            'user:id,name,apellido',
            'items.libro.master:id,titulo',
            'items.libro:id,master_id,isbn,numero_tomo',
        ]);

        return inertia('OrdenesCompra/Show', ['orden' => $ordenesCompra]);
    }

    public function confirmar(OrdenCompra $ordenesCompra)
    {
        $user = auth()->user();
        if (!$user->esAdmin() && $ordenesCompra->sucursal_id !== $user->empleado?->sucursal_id) {
            abort(403);
        }

        if ($ordenesCompra->estado !== 'borrador') {
            return back()->withErrors(['estado' => 'Solo se puede confirmar una orden en borrador.']);
        }

        $ordenesCompra->update(['estado' => 'confirmada']);

        return redirect()->route('ordenes-compra.index')
            ->with('message', "Orden {$ordenesCompra->numero_orden} confirmada.");
    }

    public function recibir(OrdenCompra $ordenesCompra)
    {
        $user = auth()->user();
        if (!$user->esAdmin() && $ordenesCompra->sucursal_id !== $user->empleado?->sucursal_id) {
            abort(403);
        }

        \DB::transaction(function () use ($ordenesCompra) {
            $fresh = OrdenCompra::with('items')->lockForUpdate()->find($ordenesCompra->id);

            if ($fresh->estado !== 'confirmada') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'estado' => 'Solo se puede recibir una orden confirmada.',
                ]);
            }

            $movimiento = \App\Models\MovimientoStock::create([
                'tipo' => 'ingreso_proveedor',
                'sucursal_destino_id' => $fresh->sucursal_id,
                'user_id' => auth()->id(),
                'motivo' => "Recepción de Orden de Compra {$fresh->numero_orden}",
            ]);

            foreach ($fresh->items as $item) {
                \App\Models\Stock::firstOrCreate(
                    ['libro_id' => $item->libro_id, 'sucursal_id' => $fresh->sucursal_id],
                    ['cantidad_disponible' => 0]
                )->increment('cantidad_disponible', $item->cantidad);

                \App\Models\MovimientoStockDetalle::create([
                    'movimiento_id' => $movimiento->id,
                    'libro_id' => $item->libro_id,
                    'cantidad' => $item->cantidad,
                    'costo_unitario' => $item->precio_unitario,
                ]);

                $libro = \App\Models\Libro::find($item->libro_id);
                if ($libro) {
                    $libro->recalcularCostoPPP($item->precio_unitario, $item->cantidad);
                }
            }

            $proveedor = \App\Models\Proveedor::find($fresh->proveedor_id);
            if (!$proveedor) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'proveedor_id' => 'El proveedor asociado ya no existe en el sistema.',
                ]);
            }
            $proveedor->increment('deuda_actual', $fresh->total);

            $fresh->update(['estado' => 'recibida']);
        });

        return redirect()->route('ordenes-compra.index')
            ->with('message', "Orden {$ordenesCompra->numero_orden} recibida. Stock y deuda actualizados.");
    }

    public function searchLibros(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim($request->get('q', ''));
        $proveedor_id = $request->get('proveedor_id');

        $query = \App\Models\Libro::with('master:id,titulo,proveedor_id')
            ->withSum('stocks', 'cantidad_disponible')
            ->withSum(['ventaDetalles as reservas_pendientes' => function($q) {
                $q->whereHas('venta', function($qVenta) {
                    $qVenta->whereIn('estado', ['pendiente', 'pagado', 'pendiente_pago']);
                });
            }], 'cantidad');

        if ($proveedor_id) {
            $query->whereHas('master', fn($query) => $query->where('proveedor_id', $proveedor_id));
        }

        if (strlen($q) > 0) {
            $query->where(function($query) use ($q) {
                $query->whereHas('master', fn($q2) => $q2->where('titulo', 'like', "%{$q}%"))
                      ->orWhere('numero_tomo', 'like', "%{$q}%");
            });
        }

        $libros = $query->select('id', 'master_id', 'numero_tomo')
            ->limit(50)
            ->get()
            ->map(fn($l) => [
                'id'       => $l->id,
                'titulo'   => $l->master->titulo . ($l->numero_tomo ? ' - Tomo ' . $l->numero_tomo : ''),
                'stock'    => $l->stocks_sum_cantidad_disponible ?? 0,
                'reservas' => $l->reservas_pendientes ?? 0,
            ]);

        return response()->json($libros);
    }

    public function destroy(OrdenCompra $ordenesCompra)
    {
        if (!in_array($ordenesCompra->estado, ['borrador', 'confirmada'])) {
            return back()->withErrors(['estado' => 'No se puede cancelar una orden ya recibida.']);
        }

        \DB::transaction(function () use ($ordenesCompra) {
            $ordenesCompra->update(['estado' => 'cancelada']);
            $ordenesCompra->delete();
        });

        return redirect()->route('ordenes-compra.index')
            ->with('message', 'Orden cancelada.');
    }
}
