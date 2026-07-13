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
        $query = Venta::with(['cliente.user', 'sucursal', 'detalles.libro.master', 'transacciones']);

        if ($request->filled('search')) {
            $search = $request->search;
            $searchNum = str_replace(['#', 'TK-', 'tk-', 'Tk-', 'tK-'], '', $search);

            $query->where(function ($q) use ($search, $searchNum) {
                if (is_numeric($searchNum)) {
                    $q->where('id', 'like', '%' . (int)$searchNum . '%');
                }
                $q->orWhereHas('cliente.user', function ($q2) use ($search) {
                    $q2->where('name', 'like', '%' . $search . '%')
                       ->orWhere('apellido', 'like', '%' . $search . '%');
                });
            });
        }

        if ($request->boolean('abonadas_pendientes')) {
            $query->where('estado', 'pagado')
                  ->where('estado_envio', 'pendiente');
        }

        $tab = $request->get('tab', 'activas');
        if ($tab === 'canceladas') {
            $query->where('estado', 'cancelado');
        } else {
            $query->where('estado', '!=', 'cancelado');
        }

        $ventas = $query->latest()->paginate(10)->withQueryString();

        $hoy      = now();
        $statsHoy = Venta::whereBetween('fecha', [$hoy->copy()->startOfDay(), $hoy->copy()->endOfDay()])
            ->where('estado', '!=', 'cancelado')
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total),0) as recaudacion, COALESCE(AVG(total),0) as promedio')
            ->first();

        $stats = [
            'ventas_hoy'       => (int)   $statsHoy->cantidad,
            'recaudacion'      => (float) $statsHoy->recaudacion,
            'promedio_ticket'  => (float) $statsHoy->promedio,
            'stock_total'      => (int)   \App\Models\Stock::sum('cantidad_disponible'),
            'total_activas'    => Venta::where('estado', '!=', 'cancelado')->count(),
            'total_canceladas' => Venta::where('estado', 'cancelado')->count(),
        ];

        return inertia('Ventas/Index', [
            'ventas'     => $ventas,
            'sucursales' => \App\Models\Sucursal::where('activo', true)->get(['id', 'nombre']),
            'stats'      => $stats,
            'filters'    => $request->only(['search', 'abonadas_pendientes', 'tab']),
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
        $sucursalId = filter_var($request->get('sucursal_id'), FILTER_VALIDATE_INT) ?: null;

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
        ->select('id', 'master_id', 'isbn', 'permite_preventa', 'numero_tomo')
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
            'numero_tomo'      => $l->numero_tomo,
            'master'           => ['titulo' => $l->master->titulo],
            'permite_preventa' => $l->permite_preventa,
            'precio_actual'    => $l->precios->first()
                ? ['precio_venta' => $l->precios->first()->precio_venta]
                : null,
            'stock_disponible' => $sucursalId ? (int) ($stocks[$l->id] ?? 0) : null,
        ]));
    }

    public function store(StoreVentaRequest $request)
    {
        $user = \Auth::user();
        $sucursal_id = $user->empleado?->sucursal_id;

        if (!$sucursal_id) {
            return redirect()->route('ventas.index')
                ->with('error', 'El usuario actual no tiene una sucursal asignada para operar.');
        }

        $libroIds = collect($request->items)->pluck('libro_id');

        try {
            \DB::transaction(function () use ($request, $libroIds, $sucursal_id) {
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

                $librosModels = \App\Models\Libro::whereIn('id', $libroIds)->get()->keyBy('id');

                $total = 0;
                foreach ($request->items as $item) {
                    $stock = \App\Models\Stock::where('libro_id', $item['libro_id'])
                        ->where('sucursal_id', $sucursal_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$stock) {
                        throw new \RuntimeException("No existe registro de stock para el libro ID {$item['libro_id']} en esta sucursal.");
                    }

                    if ($stock->cantidad_disponible <= 0 && !$librosModels[$item['libro_id']]->permite_preventa) {
                        throw new \RuntimeException("Producto agotado y sin preventa habilitada");
                    }

                    $total += $item['cantidad'] * $precios[$item['libro_id']]->precio_venta;
                }

                $usar_saldo = $request->boolean('usar_saldo_favor') && $request->medio_pago !== 'Cuenta Corriente';
                $monto_saldo_usado = 0;

                // Lock and validate CC balance before any writes
                $cliente = null;
                if ($request->cliente_id) {
                    $cliente = \App\Models\Cliente::lockForUpdate()->find($request->cliente_id);
                }

                if ($usar_saldo && $cliente && $cliente->saldo_actual > 0) {
                    $monto_saldo_usado = min($cliente->saldo_actual, $total);
                }

                $monto_restante = $total - $monto_saldo_usado;

                $estado = 'pagado';
                $motivo_pendiente = null;
                $monto_a_cobrar = $monto_restante;

                if ($request->boolean('es_excepcional') && $request->filled('motivo_pendiente')) {
                    $estado = 'pendiente_pago';
                    $motivo_pendiente = $request->motivo_pendiente;
                    if ($motivo_pendiente === 'Reserva / Seña') {
                        $monto_a_cobrar = min((float) $request->monto_sena, $monto_restante);
                    }
                }

                $origen = $request->origen === 'whatsapp' ? 'whatsapp' : 'presencial';
                if ($origen === 'whatsapp' && $request->boolean('guardar_pendiente')) {
                    $estado = 'pendiente_pago';
                    $monto_a_cobrar = 0;
                }

                $estadoEnvio = 'no_requiere';
                $direccionEnvio = null;

                if ($request->acumular_pedido) {
                    $estadoEnvio = 'no_requiere'; // No va a reparto hasta consolidar
                    $motivo_pendiente = 'Acumulación';
                } elseif ($request->requiere_envio || $request->tipo_envio === 'domicilio') {
                    $estadoEnvio = 'pendiente';
                    $sucursalModel = \App\Models\Sucursal::find($sucursal_id);
                    $localidad = $sucursalModel ? $sucursalModel->nombre : 'Desconocida';

                    $detallesDireccion = [
                        $request->destinatario_envio,
                        "Tel: " . $request->telefono_envio,
                        $request->calle_numero_envio,
                    ];

                    if ($request->filled('piso_depto_envio')) {
                        $detallesDireccion[] = $request->piso_depto_envio;
                    }

                    $detallesDireccion[] = "Localidad: " . $localidad;
                    $direccionEnvio = implode(' - ', $detallesDireccion);
                }

                $venta = Venta::create([
                    'fecha'            => now(),
                    'cliente_id'       => $request->cliente_id,
                    'user_id'          => \Auth::id(),
                    'sucursal_id'      => $sucursal_id,
                    'tipo'             => 'presencial', // kept for retro-compatibility
                    'origen'           => $origen,
                    'estado'           => $estado,
                    'estado_envio'     => $estadoEnvio,
                    'direccion_envio'  => $direccionEnvio,
                    'motivo_pendiente' => $motivo_pendiente,
                    'total'            => $total,
                ]);

                foreach ($request->items as $item) {
                    $precio = $precios[$item['libro_id']]->precio_venta;
                    $costo = $precios[$item['libro_id']]->precio_compra;

                    $venta->detalles()->create([
                        'libro_id'        => $item['libro_id'],
                        'cantidad'        => $item['cantidad'],
                        'precio_unitario' => $precio,
                        'costo_unitario'  => $costo,
                        'subtotal'        => $item['cantidad'] * $precio,
                    ]);

                    \App\Models\Stock::where('libro_id', $item['libro_id'])
                        ->where('sucursal_id', $sucursal_id)
                        ->decrement('cantidad_disponible', $item['cantidad']);
                }

                if ($monto_saldo_usado > 0) {
                    $venta->transacciones()->create([
                        'fecha'        => now(),
                        'tipo'         => 'ingreso',
                        'monto'        => $monto_saldo_usado,
                        'metodo_pago'  => 'Cuenta Corriente',
                        'sucursal_id'  => $sucursal_id,
                        'user_id'      => \Auth::id(),
                        'descripcion'  => "[Venta #{$venta->id}] - Pago con saldo a favor",
                    ]);
                    $cliente->decrement('saldo_actual', $monto_saldo_usado);
                }

                if ($monto_a_cobrar > 0) {
                    if ($request->medio_pago === 'Cuenta Corriente' && $cliente) {
                        $excedenteMetodo = $request->input('metodo_pago_excedente');
                        $nuevoSaldoProyectado = $cliente->saldo_actual - $monto_a_cobrar;

                        if ($nuevoSaldoProyectado < 0 && in_array($excedenteMetodo, ['Efectivo', 'Tarjeta', 'Transferencia'])) {
                            // Separar el pago: lo que cubre el saldo a favor disponible + el excedente en otro medio
                            $montoCC = max(0, $cliente->saldo_actual);
                            $montoRestanteExcedente = $monto_a_cobrar - $montoCC;

                            if ($montoCC > 0) {
                                $venta->transacciones()->create([
                                    'fecha'        => now(),
                                    'tipo'         => 'ingreso',
                                    'monto'        => $montoCC,
                                    'metodo_pago'  => 'Cuenta Corriente',
                                    'sucursal_id'  => $sucursal_id,
                                    'user_id'      => \Auth::id(),
                                    'descripcion'  => "[Venta #{$venta->id}] - Pago parcial Cuenta Corriente",
                                ]);
                                $cliente->decrement('saldo_actual', $montoCC);
                            }

                            $venta->transacciones()->create([
                                'fecha'        => now(),
                                'tipo'         => 'ingreso',
                                'monto'        => $montoRestanteExcedente,
                                'metodo_pago'  => $excedenteMetodo,
                                'sucursal_id'  => $sucursal_id,
                                'user_id'      => \Auth::id(),
                                'descripcion'  => "[Venta #{$venta->id}] - Excedente pagado con {$excedenteMetodo}",
                            ]);
                        } else {
                            $venta->transacciones()->create([
                                'fecha'        => now(),
                                'tipo'         => 'ingreso',
                                'monto'        => $monto_a_cobrar,
                                'metodo_pago'  => 'Cuenta Corriente',
                                'sucursal_id'  => $sucursal_id,
                                'user_id'      => \Auth::id(),
                                'descripcion'  => "[Venta #{$venta->id}]",
                            ]);
                            $cliente->decrement('saldo_actual', $monto_a_cobrar);
                        }
                    } else {
                        $venta->transacciones()->create([
                            'fecha'        => now(),
                            'tipo'         => 'ingreso',
                            'monto'        => $monto_a_cobrar,
                            'metodo_pago'  => $request->medio_pago,
                            'sucursal_id'  => $sucursal_id,
                            'user_id'      => \Auth::id(),
                            'descripcion'  => "[Venta #{$venta->id}]",
                        ]);
                    }
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
            'transacciones',
        ]);

        return inertia('Ventas/Show', ['venta' => $venta]);
    }

    public function destroy(Venta $venta)
    {
        $user = \Auth::user();
        if (!$user->esAdmin() && $user->empleado?->sucursal_id !== $venta->sucursal_id) {
            abort(403);
        }

        // Solo admins/gerentes pueden anular ventas online
        if ($venta->tipo === 'online' && !$user->esAdmin() && !$user->esGerente()) {
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

            // Solo revertimos stock y devolvemos saldo si no estaba ya cancelada (evita doble reverso)
            if ($fresh->estado !== 'cancelado') {
                foreach ($fresh->detalles as $detalle) {
                    \App\Models\Stock::where('libro_id', $detalle->libro_id)
                        ->where('sucursal_id', $fresh->sucursal_id)
                        ->increment('cantidad_disponible', $detalle->cantidad);
                }

                if ($fresh->cliente) {
                    // Devolver exactamente la suma de los montos ingresados para este ticket
                    $montoPagado = $fresh->transacciones()->where('tipo', 'ingreso')->sum('monto');
                    if ($montoPagado > 0) {
                        $fresh->cliente->increment('saldo_actual', $montoPagado);
                    }
                }
            }

            $fresh->delete();
        });

        return redirect()->route('ventas.index')
            ->with('message', 'Venta anulada y stock revertido');
    }

    public function updateEstado(Request $request, Venta $venta)
    {
        $user = \Auth::user();

        if (!$user->esAdmin() && !$user->esGerente()) {
            abort(403);
        }

        $request->validate([
            'estado' => 'sometimes|required|in:pendiente_pago,pagado,cancelado',
            'estado_envio' => 'sometimes|required|in:no_requiere,pendiente,enviado,entregado',
        ]);

        if ($request->estado === 'cancelado' && $venta->estado !== 'cancelado') {
            \DB::transaction(function () use ($venta) {
                $fresh = Venta::with(['detalles', 'cliente', 'transacciones'])
                    ->lockForUpdate()
                    ->find($venta->id);

                if (in_array($fresh->estado_envio, ['enviado', 'entregado'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'estado' => 'No se puede cancelar una venta que ya fue enviada o entregada.',
                    ]);
                }

                foreach ($fresh->detalles as $detalle) {
                    \App\Models\Stock::where('libro_id', $detalle->libro_id)
                        ->where('sucursal_id', $fresh->sucursal_id)
                        ->increment('cantidad_disponible', $detalle->cantidad);
                }

                if ($fresh->cliente) {
                    $montoPagado = $fresh->transacciones()->where('tipo', 'ingreso')->sum('monto');
                    if ($montoPagado > 0) {
                        $fresh->cliente->increment('saldo_actual', $montoPagado);
                    }
                }

                $fresh->update(['estado' => 'cancelado']);
            });
            return back()->with('message', 'Venta cancelada y stock revertido.');
        }

        if ($venta->estado === 'cancelado' && $request->estado !== 'cancelado') {
            return back()->with('error', 'No se puede modificar el estado de una venta cancelada.');
        }

        $updates = [];
        if ($request->has('estado')) {
            $updates['estado'] = $request->estado;
        }
        if ($request->has('estado_envio')) {
            $updates['estado_envio'] = $request->estado_envio;
        }

        if (!empty($updates)) {
            $venta->update($updates);
        }

        return back()->with('message', 'Estado de venta actualizado.');
    }
}
