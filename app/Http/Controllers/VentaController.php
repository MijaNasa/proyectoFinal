<?php

namespace App\Http\Controllers;

use App\Models\PrecioLibro;
use App\Models\Venta;
use App\Http\Requests\StoreVentaRequest;
use Illuminate\Http\Request;
use App\Traits\GeocodeHelper;

class VentaController extends Controller
{
    use GeocodeHelper;
    public function index(Request $request)
    {
        $sucursalId = $request->user()->sucursalRestringidaId();

        $query = Venta::with(['cliente.user', 'user', 'sucursal', 'detalles.libro.master', 'transacciones'])
            ->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId));

        if ($request->filled('search')) {
            $search = $request->search;
            $searchNum = str_replace(['#', 'TK-', 'tk-', 'Tk-', 'tK-'], '', $search);

            $query->where(function ($q) use ($search, $searchNum) {
                if (is_numeric($searchNum)) {
                    $q->where('id', 'like', '%' . (int)$searchNum . '%');
                }
                $q->orWhereHas('cliente.user', function ($q2) use ($search) {
                    $like = '%' . mb_strtolower($search) . '%';
                    $q2->whereRaw('LOWER(name) LIKE ?', [$like])
                       ->orWhereRaw('LOWER(apellido) LIKE ?', [$like]);
                });
            });
        }



        if ($request->filled('estados')) {
            $estados = is_array($request->estados) ? $request->estados : [$request->estados];
            $query->whereIn('estado', $estados);
        } else {
            $tab = $request->get('tab', 'activas');
            if ($tab === 'canceladas') {
                $query->where('estado', 'cancelado');
            } elseif ($tab === 'finalizadas') {
                $query->where('estado', 'finalizado');
            } else {
                $query->whereNotIn('estado', ['cancelado', 'finalizado']);
            }
        }

        $ventas = $query->latest()->paginate(10)->withQueryString();

        $hoy      = now();
        $statsHoy = Venta::whereBetween('fecha', [$hoy->copy()->startOfDay(), $hoy->copy()->endOfDay()])
            ->where('estado', '!=', 'cancelado')
            ->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total),0) as recaudacion, COALESCE(AVG(total),0) as promedio')
            ->first();

        $stats = [
            'ventas_hoy'       => (int)   $statsHoy->cantidad,
            'recaudacion'      => (float) $statsHoy->recaudacion,
            'promedio_ticket'  => (float) $statsHoy->promedio,
            'total_activas'    => Venta::whereNotIn('estado', ['cancelado', 'finalizado'])->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))->count(),
            'total_finalizadas'=> Venta::where('estado', 'finalizado')->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))->count(),
            'total_canceladas' => Venta::where('estado', 'cancelado')->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))->count(),
        ];

        return inertia('Ventas/Index', [
            'ventas'     => $ventas,
            'sucursales' => \App\Models\Sucursal::where('activo', true)->when($sucursalId, fn($q) => $q->where('id', $sucursalId))->get(['id', 'nombre']),
            'stats'      => $stats,
            'filters'    => $request->only(['search', 'tab', 'estados']),
        ]);
    }

    public function searchClientes(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim($request->get('q', ''));

        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $like = '%' . mb_strtolower($q) . '%';

        $clientes = \App\Models\Cliente::with('user:id,name,apellido,email,dni')
            ->whereHas('user', fn($query) => $query
                ->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(apellido) LIKE ?', [$like])
                ->orWhereRaw('LOWER(dni) LIKE ?', [$like])
                ->orWhereRaw('LOWER(email) LIKE ?', [$like])
            )
            ->select('id', 'user_id', 'saldo_actual')
            ->with(['suscripciones' => fn($q) => $q->where('estado', 'activa')])
            ->limit(10)
            ->get()
            ->map(fn($c) => [
                'id'               => $c->id,
                'saldo_actual'     => $c->saldo_actual,
                'suscripciones'    => $c->suscripciones->map(fn($s) => [
                    'libro_master_id' => $s->libro_master_id,
                    'tomo_inicio'     => $s->tomo_inicio ?? 1,
                ])->toArray(),
                'libros_comprados' => \App\Models\VentaDetalle::whereHas('venta', fn($q) => $q->where('cliente_id', $c->id)->where('estado', '!=', 'cancelado'))->pluck('libro_id')->unique()->values()->toArray(),
                'user'             => [
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

        if (!$sucursalId) {
            $sucursalId = $request->user()->empleado?->sucursal_id ?? \App\Models\Sucursal::where('activo', true)->first()?->id;
        }

        if (strlen($q) < 1) {
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
        ->where(function ($query) use ($q) {
            $like = '%' . mb_strtolower($q) . '%';
            $query->whereHas('master', fn($q2) => $q2->whereRaw('LOWER(titulo) LIKE ?', [$like]))
                ->orWhereRaw('LOWER(isbn) LIKE ?', [$like]);
        })
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
                ? ['precio_venta' => $l->permite_preventa ? ($l->precios->first()->precio_venta * 0.90) : $l->precios->first()->precio_venta]
                : null,
            'stock_disponible' => $sucursalId ? (int) ($stocks[$l->id] ?? 0) : null,
        ]));
    }

    public function store(StoreVentaRequest $request)
    {
        $user = \Auth::user();
        // Un admin no esta atado a una sola sucursal: puede elegir desde cual vende.
        // Un empleado normal siempre opera desde la suya, sin importar que mande el request.
        $sucursal_id = $user->esAdmin()
            ? ((int) $request->sucursal_id ?: $user->empleado?->sucursal_id)
            : $user->empleado?->sucursal_id;

        if (!$sucursal_id) {
            return redirect()->route('ventas.index')
                ->with('error', $user->esAdmin()
                    ? 'Seleccioná una sucursal para operar.'
                    : 'El usuario actual no tiene una sucursal asignada para operar.');
        }

        $libroIds = collect($request->items)->pluck('libro_id');

        try {
            $venta = \DB::transaction(function () use ($request, $libroIds, $sucursal_id) {
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

                // 1. Validar Stock y Lock
                $tienePreventas = false;
                foreach ($request->items as $item) {
                    if ($librosModels[$item['libro_id']]->permite_preventa) {
                        $tienePreventas = true;
                        continue;
                    }
                    $stock = \App\Models\Stock::where('libro_id', $item['libro_id'])
                        ->where('sucursal_id', $sucursal_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$stock) {
                        throw new \RuntimeException("No existe registro de stock para el libro ID {$item['libro_id']} en esta sucursal.");
                    }

                    if ($stock->cantidad_disponible <= 0) {
                        throw new \RuntimeException("Producto agotado y sin preventa habilitada");
                    }
                }

                // 2. Procesar Descuentos por Suscripción y Calcular Total
                $suscripciones = collect();
                $compradosIds = [];
                
                if ($request->cliente_id) {
                    $suscripciones = \App\Models\Suscripcion::where('cliente_id', $request->cliente_id)
                        ->where('estado', 'activa')
                        ->get()
                        ->keyBy('libro_master_id');

                    $compradosIds = \App\Models\VentaDetalle::whereHas('venta', function ($q) use ($request) {
                        $q->where('cliente_id', $request->cliente_id)
                          ->where('estado', '!=', 'cancelado');
                    })->whereIn('libro_id', $libroIds)->pluck('libro_id')->toArray();
                }

                $processedItems = [];
                $total = 0;

                foreach ($request->items as $item) {
                    $libroId = $item['libro_id'];
                    $cantidad = $item['cantidad'];
                    $libroModel = $librosModels[$libroId];
                    $precioOriginal = $libroModel->permite_preventa ? $precios[$libroId]->precio_venta * 0.90 : $precios[$libroId]->precio_venta;
                    $costoOriginal = $precios[$libroId]->precio_compra;

                    $hasDiscount = false;
                    
                    // Verificar si aplica el descuento por suscripción (5% adicional para suscriptores de la serie)
                    $sub = $suscripciones->get($libroModel->master_id);
                    if ($request->cliente_id && $sub && !in_array($libroId, $compradosIds)) {
                        $rawTomo = (string) ($libroModel->numero_tomo ?? '1');
                        $numTomo = (int) preg_replace('/\D/', '', $rawTomo) ?: 1;
                        $tomoInicio = $sub->tomo_inicio ?? 1;
                        if ($numTomo >= $tomoInicio) {
                            $hasDiscount = true;
                        }
                    }

                    if ($hasDiscount) {
                        $precioDescuento = round($precioOriginal * 0.95, 2);
                        
                        $processedItems[] = [
                            'libro_id' => $libroId,
                            'cantidad' => 1,
                            'precio_venta' => $precioDescuento,
                            'precio_compra' => $costoOriginal,
                        ];
                        $total += $precioDescuento;
                        
                        if ($cantidad > 1) {
                            $restante = $cantidad - 1;
                            $processedItems[] = [
                                'libro_id' => $libroId,
                                'cantidad' => $restante,
                                'precio_venta' => $precioOriginal,
                                'precio_compra' => $costoOriginal,
                            ];
                            $total += ($restante * $precioOriginal);
                        }
                    } else {
                        $processedItems[] = [
                            'libro_id' => $libroId,
                            'cantidad' => $cantidad,
                            'precio_venta' => $precioOriginal,
                            'precio_compra' => $costoOriginal,
                        ];
                        $total += ($cantidad * $precioOriginal);
                    }
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

                $estado = 'finalizado';
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

                $direccionEnvio = null;

                if ($request->acumular_pedido) {
                    $motivo_pendiente = 'Acumulación';
                } elseif ($request->requiere_envio || $request->tipo_envio === 'domicilio') {
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
                    'direccion_envio'  => $direccionEnvio,
                    'motivo_pendiente' => $motivo_pendiente,
                    'metodo_pago'      => $request->medio_pago,
                    'total'            => $total,
                ]);

                foreach ($processedItems as $item) {
                    $precio = $item['precio_venta'];
                    $costo = $item['precio_compra'];

                    $venta->detalles()->create([
                        'libro_id'        => $item['libro_id'],
                        'cantidad'        => $item['cantidad'],
                        'precio_unitario' => $precio,
                        'costo_unitario'  => $costo,
                        'subtotal'        => $item['cantidad'] * $precio,
                    ]);

                    if (!$librosModels[$item['libro_id']]->permite_preventa) {
                        \App\Models\Stock::where('libro_id', $item['libro_id'])
                            ->where('sucursal_id', $sucursal_id)
                            ->decrement('cantidad_disponible', $item['cantidad']);
                    }
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

                return $venta;
            });
        } catch (\RuntimeException $e) {
            return redirect()->route('ventas.index')
                ->with('error', $e->getMessage());
        }

        if (isset($venta) && $venta) {
            try {
                $usuariosNotificar = \App\Models\User::where('activo', true)
                    ->get()
                    ->filter(fn($u) => $u->esAdmin() || $u->esGerente() || ($u->empleado && $u->empleado->sucursal_id == $venta->sucursal_id));
                if ($usuariosNotificar->isNotEmpty()) {
                    \Illuminate\Support\Facades\Notification::send($usuariosNotificar, new \App\Notifications\NuevaVentaNotification($venta));
                }
            } catch (\Throwable $e) {
                \Log::warning('Error enviando notificacion de nueva venta presencial: ' . $e->getMessage());
            }
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
            'detalles.libro:id,master_id,isbn,numero_tomo',
            'transacciones',
        ]);

        return inertia('Ventas/Show', ['venta' => $venta]);
    }

    public function generarComprobantePdf(Venta $venta)
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
            'detalles.libro:id,master_id,isbn,numero_tomo',
            'transacciones',
        ]);

        $metodoPago = $venta->transacciones->first()->metodo_pago ?? '—';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.comprobante_venta', compact('venta', 'metodoPago'));

        return $pdf->stream('Comprobante_Venta_' . str_pad($venta->id, 6, '0', STR_PAD_LEFT) . '.pdf', ['Attachment' => false]);
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
            $fresh->cancelarConRestitucionDeStock();

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
        if (!$user->esAdmin() && $user->empleado?->sucursal_id !== $venta->sucursal_id) {
            abort(403);
        }

        $request->validate([
            'estado' => 'sometimes|required|in:pendiente_pago,en_preventa,esperando_traslado,en_preparacion,listo_para_retiro,acumulado,enviado,finalizado,cancelado',
            'direccion_envio' => 'nullable|string|max:500',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
        ]);

        $nuevoEstado = $request->estado;
        $estadoActual = $venta->estado;

        if ($nuevoEstado && $nuevoEstado !== $estadoActual) {
            $permitidos = Venta::TRANSICIONES[$estadoActual] ?? [];
            if (!in_array($nuevoEstado, $permitidos)) {
                return back()->with('error', 'No es posible pasar del estado ' . strtoupper(str_replace('_', ' ', $estadoActual)) . ' a ' . strtoupper(str_replace('_', ' ', $nuevoEstado)) . '.');
            }
        }

        if ($request->estado === 'cancelado' && $venta->estado !== 'cancelado') {
            \DB::transaction(function () use ($venta) {
                $fresh = $venta->fresh();
                $fresh->cancelarConRestitucionDeStock();
            });
            return back()->with('message', 'Venta cancelada y stock revertido.');
        }

        $updates = [];
        if ($request->has('estado')) {
            $updates['estado'] = $request->estado;
            
            if ($request->estado === 'en_preparacion') {
                if (!$venta->tipo_envio) {
                    $updates['tipo_envio'] = 'domicilio';
                }
                if (!$request->direccion_envio && !$venta->direccion_envio) {
                    return back()->with('error', 'Se requiere una dirección de envío para poner la venta en preparación.');
                }
            } elseif ($request->estado === 'acumulado') {
                $updates['tipo_envio'] = 'acumulacion';
            } elseif ($request->estado === 'listo_para_retiro') {
                $updates['tipo_envio'] = 'retiro';
            }
        }
        
        if ($request->has('direccion_envio') && $request->direccion_envio) {
            // Si el autocomplete (Photon) ya mando coordenadas, las usamos directo
            // en vez de volver a geocodificar el texto contra Nominatim.
            $lat = $request->latitud;
            $lon = $request->longitud;
            if (!$lat || !$lon) {
                $coords = $this->geocodeAddress($request->direccion_envio);
                $lat = $coords['lat'] ?? null;
                $lon = $coords['lon'] ?? null;
            }

            if ($lat && $lon) {
                $latSucursal = -32.9493; // San Martin 843, Rosario
                $lonSucursal = -60.6382;
                $distancia = $this->calculateDistance($latSucursal, $lonSucursal, $lat, $lon);

                if ($distancia > 20 && !in_array($venta->tipo_envio, ['correo_nacional', 'correo_sucursal'])) {
                    $updates['tipo_envio'] = 'correo_nacional';
                    if (!$venta->costo_envio) {
                        $updates['costo_envio'] = 50000;
                    }
                }

                $updates['latitud'] = $lat;
                $updates['longitud'] = $lon;
            }
            $updates['direccion_envio'] = $request->direccion_envio;
        }

        // Si mandan el tracking (desde el panel de la venta de correo)
        if ($request->has('tracking_code')) {
            $updates['tracking_code'] = $request->tracking_code;
        }

        if (!empty($updates)) {
            $venta->update($updates);

            // Si la venta ya estaba asignada a una parada de reparto, propagar las coordenadas nuevas
            if (isset($updates['latitud'], $updates['longitud'])) {
                $venta->paradas()->update(['latitud' => $updates['latitud'], 'longitud' => $updates['longitud']]);
            }
        }

        return back()->with('message', 'Estado de venta actualizado.');
    }

    public function confirmarPago(Request $request, Venta $venta)
    {
        $user = \Auth::user();

        if (!$user->esAdmin() && !$user->esGerente()) {
            abort(403);
        }
        if (!$user->esAdmin() && $user->empleado?->sucursal_id !== $venta->sucursal_id) {
            abort(403);
        }

        if ($venta->estado !== 'pendiente_pago') {
            return back()->with('error', 'La venta no está pendiente de pago.');
        }

        try {
            \DB::transaction(function () use ($venta, $user) {
                $fresh = Venta::with('detalles.libro')->lockForUpdate()->find($venta->id);
                if ($fresh->estado !== 'pendiente_pago') return;

                // Calculate pending amount
                $montoAbonado = $fresh->transacciones()->where('tipo', 'ingreso')->sum('monto');
                $restante = $fresh->total - $montoAbonado;

                if ($restante > 0) {
                    $fresh->transacciones()->create([
                        'fecha'        => now(),
                        'tipo'         => 'ingreso',
                        'monto'        => $restante,
                        'metodo_pago'  => $fresh->metodo_pago ?? 'Transferencia',
                        'sucursal_id'  => $fresh->sucursal_id,
                        'user_id'      => $user->id,
                        'descripcion'  => "[Venta Online #{$fresh->id}] - Pago Confirmado Manualmente",
                    ]);
                }

                $tienePreventas = $fresh->detalles->contains(fn($detalle) => $detalle->libro && $detalle->libro->permite_preventa);

                if ($tienePreventas) {
                    $nuevoEstado = 'en_preventa';
                } else {
                    // Verify if there are pending transfers
                    $tieneTraslados = \App\Models\TransferenciaStock::where('venta_id', $fresh->id)->exists();
                    $nuevoEstado = $tieneTraslados ? 'esperando_traslado' : 'en_preparacion';
                    if (!$tieneTraslados) {
                        if ($fresh->tipo_envio === 'retiro') {
                            $nuevoEstado = 'listo_para_retiro';
                        } elseif ($fresh->tipo_envio === 'acumulacion') {
                            $nuevoEstado = 'acumulado';
                        }
                    }

                    if ($tieneTraslados) {
                        \App\Models\TransferenciaStock::where('venta_id', $fresh->id)
                            ->where('estado', 'pendiente')
                            ->update(['estado' => 'pendiente_envio']);

                        $usuariosNotificar = \App\Models\User::where('activo', true)->get()->filter(fn($u) => $u->esAdmin() || $u->esGerente());
                        \Illuminate\Support\Facades\Notification::send($usuariosNotificar, new \App\Notifications\TrasladoPendienteVenta($fresh));
                    }
                }

                $fresh->update([
                    'estado' => $nuevoEstado,
                    'pago_expira_at' => null
                ]);
            });
        } catch (\Throwable $e) {
            \Log::error('confirmarPago: excepción', [
                'venta_id' => $venta->id,
                'error'    => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
            ]);

            // Temporal: mostramos el detalle del error al admin/gerente para diagnosticar
            // sin depender de los logs de Render (no tenemos acceso directo).
            return back()->with('error', 'Error al confirmar el pago: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
        }

        return back()->with('message', 'Pago confirmado correctamente.');
    }

    public function destroyCanceladas()
    {
        $user = \Auth::user();
        if (!$user->esAdmin() && !$user->esGerente()) {
            abort(403);
        }

        \App\Models\Venta::where('estado', 'cancelado')
            ->when($user->sucursalRestringidaId(), fn($q, $sid) => $q->where('sucursal_id', $sid))
            ->delete();

        return redirect()->back()->with('message', 'Historial de ventas canceladas limpiado exitosamente.');
    }
}
