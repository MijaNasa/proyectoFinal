<?php

namespace App\Http\Controllers;

use App\Models\PrecioLibro;
use App\Models\Venta;
use App\Http\Requests\StoreVentaRequest;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    public function index(Request $request)
    {
        $query = Venta::with(['cliente.user', 'sucursal', 'detalles.libro.master']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('cliente.user', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('apellido', 'like', '%' . $search . '%');
            });
        }

        $ventas = $query->latest()->paginate(10)->withQueryString();

        $hoy      = now();
        $statsHoy = Venta::whereBetween('fecha', [$hoy->copy()->startOfDay(), $hoy->copy()->endOfDay()])
            ->whereNotIn('estado', ['cancelado', 'pendiente_pago'])
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total),0) as recaudacion, COALESCE(AVG(total),0) as promedio')
            ->first();

        $stats = [
            'ventas_hoy'      => (int)   $statsHoy->cantidad,
            'recaudacion'     => (float) $statsHoy->recaudacion,
            'promedio_ticket' => (float) $statsHoy->promedio,
            'stock_total'     => (int)   \App\Models\Stock::sum('cantidad_disponible'),
        ];

        return inertia('Ventas/Index', [
            'ventas'     => $ventas,
            'sucursales' => \App\Models\Sucursal::where('activo', true)->get(['id', 'nombre']),
            'stats'      => $stats,
            'filters'    => $request->only(['search']),
        ]);
    }

    public function searchClientes(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim($request->get('q', ''));

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $clientes = \App\Models\Cliente::with('user:id,name,apellido,email,dni')
            ->whereHas('user', fn($query) => $query
                ->where('name', 'like', "%{$q}%")
                ->orWhere('apellido', 'like', "%{$q}%")
                ->orWhere('dni', 'like', "%{$q}%")
            )
            ->select('id', 'user_id', 'saldo_actual')
            ->limit(10)
            ->get()
            ->map(fn($c) => [
                'id'           => $c->id,
                'saldo_actual' => $c->saldo_actual,
                'user'         => [
                    'name'     => $c->user->name,
                    'apellido' => $c->user->apellido,
                    'email'    => $c->user->email,
                    'dni'      => $c->user->dni,
                ],
            ]);

        return response()->json($clientes);
    }

    public function searchLibros(Request $request): \Illuminate\Http\JsonResponse
    {
        $q          = trim($request->get('q', ''));
        $sucursalId = $request->get('sucursal_id');

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $libros = \App\Models\Libro::with([
            'master:id,titulo',
            'precios' => fn($query) => $query
                ->where('activo', true)
                ->where(fn($sq) => $sq->whereNull('fecha_hasta')->orWhere('fecha_hasta', '>', now()))
                ->latest('fecha_desde')
                ->limit(1),
        ])
        ->where(fn($query) => $query
            ->whereHas('master', fn($q2) => $q2->where('titulo', 'like', "%{$q}%"))
            ->orWhere('isbn', 'like', "%{$q}%")
        )
        ->select('id', 'master_id', 'isbn')
        ->limit(20)
        ->get();

        // One extra query to load stock for all results at once (avoids N+1)
        $stocks = [];
        if ($sucursalId) {
            $stocks = \App\Models\Stock::where('sucursal_id', $sucursalId)
                ->whereIn('libro_id', $libros->pluck('id'))
                ->pluck('cantidad_disponible', 'libro_id')
                ->toArray();
        }

        return response()->json($libros->map(fn($l) => [
            'id'               => $l->id,
            'isbn'             => $l->isbn,
            'master'           => ['titulo' => $l->master->titulo],
            'precio_actual'    => $l->precios->first()
                ? ['precio_venta' => $l->precios->first()->precio_venta]
                : null,
            'stock_disponible' => $sucursalId ? (int) ($stocks[$l->id] ?? 0) : null,
        ]));
    }

    public function store(StoreVentaRequest $request)
    {
        $user = \Auth::user();
        if (!$user->esAdmin() && (int) $request->sucursal_id !== $user->empleado?->sucursal_id) {
            abort(403);
        }

        $libroIds = collect($request->items)->pluck('libro_id');

        try {
            \DB::transaction(function () use ($request, $libroIds) {
                // Read prices inside the transaction so no stale-price sale is possible
                $precios = PrecioLibro::whereIn('libro_id', $libroIds)
                    ->where('activo', true)
                    ->where(fn($q) => $q->whereNull('fecha_hasta')->orWhere('fecha_hasta', '>', now()))
                    ->orderByDesc('fecha_desde')
                    ->get()
                    ->unique('libro_id')
                    ->keyBy('libro_id');

                foreach ($libroIds as $libroId) {
                    if (!isset($precios[$libroId])) {
                        throw new \RuntimeException("El libro ID {$libroId} no tiene un precio activo.");
                    }
                }

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

                // Lock and validate CC balance before any writes
                $cliente = null;
                if ($request->medio_pago === 'Cuenta Corriente') {
                    $cliente = \App\Models\Cliente::lockForUpdate()->find($request->cliente_id);
                    if (!$cliente) {
                        throw new \RuntimeException("El cliente seleccionado no existe.");
                    }
                    if ($cliente->saldo_actual < $total) {
                        throw new \RuntimeException(
                            'Saldo insuficiente en Cuenta Corriente. Disponible: $' .
                            number_format($cliente->saldo_actual, 2, ',', '.')
                        );
                    }
                }

                $venta = Venta::create([
                    'fecha'       => now(),
                    'cliente_id'  => $request->cliente_id,
                    'user_id'     => \Auth::id(),
                    'sucursal_id' => $request->sucursal_id,
                    'tipo'        => 'presencial',
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
                    'fecha'        => now(),
                    'tipo'         => 'ingreso',
                    'monto'        => $total,
                    'metodo_pago'  => $request->medio_pago,
                    'sucursal_id'  => $request->sucursal_id,
                    'user_id'      => \Auth::id(),
                    'descripcion'  => "[Venta #{$venta->id}]",
                ]);

                if ($cliente) {
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

    public function destroy(Venta $venta)
    {
        $user = \Auth::user();
        if (!$user->esAdmin() && $user->empleado?->sucursal_id !== $venta->sucursal_id) {
            abort(403);
        }

        \DB::transaction(function () use ($venta) {
            $fresh = Venta::with(['detalles', 'cliente', 'transacciones'])
                ->lockForUpdate()
                ->find($venta->id);

            if (!$fresh) return;

            if (in_array($fresh->estado, ['enviado', 'entregado'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'estado' => 'No se puede anular una venta que ya fue enviada o entregada.',
                ]);
            }

            foreach ($fresh->detalles as $detalle) {
                \App\Models\Stock::where('libro_id', $detalle->libro_id)
                    ->where('sucursal_id', $fresh->sucursal_id)
                    ->increment('cantidad_disponible', $detalle->cantidad);
            }

            $trans = $fresh->transacciones->firstWhere('metodo_pago', 'Cuenta Corriente');
            if ($trans && $fresh->cliente) {
                $fresh->cliente->increment('saldo_actual', $trans->monto);
            }

            // Eliminar transacciones para que no inflen el monto esperado del cierre de caja
            $fresh->transacciones()->delete();
            $fresh->delete();
        });

        return redirect()->route('ventas.index')
            ->with('message', 'Venta anulada y stock revertido');
    }
}
