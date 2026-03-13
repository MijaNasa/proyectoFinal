<?php

namespace App\Http\Controllers;

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

        // Real Stats
        $stats = [
            'ventas_hoy' => Venta::whereDate('fecha', now())->count(),
            'recaudacion' => Venta::whereDate('fecha', now())->sum('total'),
            'promedio_ticket' => (float) Venta::whereDate('fecha', now())->avg('total') ?: 0,
            'stock_total' => (int) \App\Models\Stock::sum('cantidad_disponible')
        ];

        return inertia('Ventas/Index', [
            'ventas' => $ventas,
            'clientes' => \App\Models\Cliente::with('user')->get(),
            'sucursales' => \App\Models\Sucursal::where('activo', true)->get(),
            'libros' => \App\Models\Libro::with(['master', 'precios' => function($q) {
                $q->where('activo', true)->where(function($sq) {
                    $sq->whereNull('fecha_hasta')->orWhere('fecha_hasta', '>', now());
                })->latest('fecha_desde');
            }])->get()->map(function($l) {
                $l->precio_actual = $l->precios->first();
                return $l;
            }),
            'stats' => $stats,
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVentaRequest $request)
    {
        \DB::transaction(function() use ($request) {
            $total = 0;
            foreach ($request->items as $item) {
                $total += $item['cantidad'] * $item['precio'];
            }

            $venta = Venta::create([
                'fecha' => now(),
                'cliente_id' => $request->cliente_id,
                'user_id' => \Auth::id(),
                'sucursal_id' => $request->sucursal_id,
                'tipo' => $request->tipo,
                'total' => $total,
            ]);

            foreach ($request->items as $item) {
                $venta->detalles()->create([
                    'libro_id' => $item['libro_id'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio'],
                    'subtotal' => $item['cantidad'] * $item['precio'],
                ]);

                // Update Stock
                $stock = \App\Models\Stock::where('libro_id', $item['libro_id'])
                    ->where('sucursal_id', $request->sucursal_id)
                    ->first();

                if ($stock) {
                    $stock->decrement('cantidad_disponible', $item['cantidad']);
                }
            }

            // Financial Transaction
            $venta->transacciones()->create([
                'fecha' => now(),
                'tipo' => 'ingreso',
                'monto' => $total,
                'metodo_pago' => $request->medio_pago,
                'sucursal_id' => $request->sucursal_id,
                'user_id' => \Auth::id(),
            ]);

            // Update Client Balance if "Cuenta Corriente"
            if ($request->medio_pago === 'Cuenta Corriente') {
                $cliente = \App\Models\Cliente::find($request->cliente_id);
                $cliente->decrement('saldo_actual', $total);
            }
        });

        return redirect()->route('ventas.index')
            ->with('message', 'Venta procesada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Venta $venta)
    {
        \DB::transaction(function() use ($venta) {
            // Revert stocks
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
