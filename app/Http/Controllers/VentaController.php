<?php

namespace App\Http\Controllers;

use App\Models\PrecioLibro;
use App\Models\Venta;
use App\Http\Requests\StoreVentaRequest;
use App\Http\Requests\UpdateVentaRequest;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Venta::with(['cliente.user', 'sucursal', 'detalles.libro.master']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('cliente.user', function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('apellido', 'like', '%' . $search . '%');
            });
        }

        $ventas = $query->latest()->paginate(10)->withQueryString();

        $hoy = now();
        $statsHoy = Venta::whereBetween('fecha', [$hoy->copy()->startOfDay(), $hoy->copy()->endOfDay()])
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total),0) as recaudacion, COALESCE(AVG(total),0) as promedio')
            ->first();

        $stats = [
            'ventas_hoy'      => (int) $statsHoy->cantidad,
            'recaudacion'     => (float) $statsHoy->recaudacion,
            'promedio_ticket' => (float) $statsHoy->promedio,
            'stock_total'     => (int) \App\Models\Stock::sum('cantidad_disponible'),
        ];

        return inertia('Ventas/Index', [
            'ventas'     => $ventas,
            'clientes'   => \App\Models\Cliente::with('user:id,name,apellido,email')
                               ->get(['id', 'user_id', 'saldo_actual']),
            'sucursales' => \App\Models\Sucursal::where('activo', true)->get(['id', 'nombre']),
            'libros'     => \App\Models\Libro::with([
                               'master:id,titulo',
                               'precios' => fn($q) => $q->where('activo', true)
                                   ->where(fn($sq) => $sq->whereNull('fecha_hasta')->orWhere('fecha_hasta', '>', now()))
                                   ->latest('fecha_desde')
                                   ->limit(1),
                           ])->get(['id', 'master_id', 'isbn'])->map(function ($l) {
                               $l->precio_actual = $l->precios->first();
                               return $l;
                           }),
            'stats'      => $stats,
            'filters'    => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVentaRequest $request)
    {
        $libroIds = collect($request->items)->pluck('libro_id');

        $precios = PrecioLibro::whereIn('libro_id', $libroIds)
            ->where('activo', true)
            ->where(function ($q) {
                $q->whereNull('fecha_hasta')->orWhere('fecha_hasta', '>', now());
            })
            ->orderByDesc('fecha_desde')
            ->get()
            ->unique('libro_id')
            ->keyBy('libro_id');

        foreach ($libroIds as $libroId) {
            if (!isset($precios[$libroId])) {
                return back()->withErrors(['items' => "El libro ID {$libroId} no tiene un precio activo."]);
            }
        }

        try {
        \DB::transaction(function () use ($request, $precios) {
            $total = 0;
            foreach ($request->items as $item) {
                $stock = \App\Models\Stock::where('libro_id', $item['libro_id'])
                    ->where('sucursal_id', $request->sucursal_id)
                    ->lockForUpdate()
                    ->first();

                if (!$stock || $stock->cantidad_disponible < $item['cantidad']) {
                    throw new \RuntimeException("Stock insuficiente para el libro ID {$item['libro_id']}.");
                }

                $total += $item['cantidad'] * $precios[$item['libro_id']]->precio_venta;
            }

            $venta = Venta::create([
                'fecha'       => now(),
                'cliente_id'  => $request->cliente_id,
                'user_id'     => \Auth::id(),
                'sucursal_id' => $request->sucursal_id,
                'tipo'        => $request->tipo,
                'total'       => $total,
            ]);

            foreach ($request->items as $item) {
                $precio = $precios[$item['libro_id']]->precio_venta;

                $venta->detalles()->create([
                    'libro_id'        => $item['libro_id'],
                    'cantidad'        => $item['cantidad'],
                    'precio_unitario' => $precio,
                    'subtotal'        => $item['cantidad'] * $precio,
                ]);

                \App\Models\Stock::where('libro_id', $item['libro_id'])
                    ->where('sucursal_id', $request->sucursal_id)
                    ->decrement('cantidad_disponible', $item['cantidad']);
            }

            $venta->transacciones()->create([
                'fecha'       => now(),
                'tipo'        => 'ingreso',
                'monto'       => $total,
                'metodo_pago' => $request->medio_pago,
                'sucursal_id' => $request->sucursal_id,
                'user_id'     => \Auth::id(),
            ]);

            if ($request->medio_pago === 'Cuenta Corriente') {
                $cliente = \App\Models\Cliente::find($request->cliente_id);
                $cliente->decrement('saldo_actual', $total);
            }
        });
        } catch (\RuntimeException $e) {
            return redirect()->route('ventas.index')
                ->with('error', $e->getMessage());
        }

        return redirect()->route('ventas.index')
            ->with('message', 'Venta procesada con éxito');
    }

    public function show(Venta $venta): \Inertia\Response
    {
        $user = \Auth::user();
        if (!$user->esAdmin() && $user->empleado?->sucursal_id !== $venta->sucursal_id) {
            abort(403);
        }

        $venta->load([
            'cliente.user:id,name,apellido,email',
            'user:id,name,apellido',
            'sucursal:id,nombre,calle,numero,telefono',
            'detalles.libro.master:id,titulo',
            'detalles.libro:id,master_id,isbn',
            'transacciones:id,transaccionable_id,transaccionable_type,metodo_pago,monto',
        ]);

        return inertia('Ventas/Show', ['venta' => $venta]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Venta $venta)
    {
        $user = \Auth::user();
        if (!$user->esAdmin() && $user->empleado?->sucursal_id !== $venta->sucursal_id) {
            abort(403);
        }

        $venta->load(['detalles', 'cliente', 'transacciones']);

        \DB::transaction(function() use ($venta) {
            foreach ($venta->detalles as $detalle) {
                \App\Models\Stock::where('libro_id', $detalle->libro_id)
                    ->where('sucursal_id', $venta->sucursal_id)
                    ->increment('cantidad_disponible', $detalle->cantidad);
            }
            
            // Revert client balance if CC
            $trans = $venta->transacciones()->where('metodo_pago', 'Cuenta Corriente')->first();
            if ($trans) {
                $venta->cliente->increment('saldo_actual', $trans->monto);
            }

            $venta->delete();
        });

        return redirect()->route('ventas.index')
            ->with('message', 'Venta anulada y stock revertido');
    }
}
