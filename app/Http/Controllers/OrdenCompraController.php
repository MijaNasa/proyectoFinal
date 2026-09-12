<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use Illuminate\Http\Request;

class OrdenCompraController extends Controller
{
    public function index(Request $request)
    {
        $sucursalId = $request->user()->sucursalRestringidaId();

        $query = OrdenCompra::with([
            'proveedor', 'sucursal', 'user:id,name,apellido',
            'items.libro.master:id,titulo', 'items.libro:id,master_id,isbn,numero_tomo'
        ])
            ->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))
            ->latest();

        if ($request->filled('search')) {
            $like = '%' . mb_strtolower($request->search) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(numero_orden) LIKE ?', [$like])
                  ->orWhereHas('proveedor', fn($q2) => $q2->whereRaw('LOWER(nombre_empresa) LIKE ?', [$like]));
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $ordenes = $query->paginate(15)->withQueryString();

        $stats = [
            'total'      => OrdenCompra::when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))->count(),
            'borradores' => OrdenCompra::where('estado', 'borrador')->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))->count(),
            'confirmadas'=> OrdenCompra::where('estado', 'confirmada')->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))->count(),
            'recibidas'  => OrdenCompra::where('estado', 'recibida')->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))->count(),
        ];

        return inertia('OrdenesCompra/Index', [
            'ordenes'     => $ordenes,
            'proveedores' => \App\Models\Proveedor::where('activo', true)->orderBy('nombre_empresa')->get(['id', 'nombre_empresa', 'nombre_empresa as nombre']),
            'sucursales'  => \App\Models\Sucursal::where('activo', true)->when($sucursalId, fn($q) => $q->where('id', $sucursalId))->get(['id', 'nombre']),
            'stats'       => $stats,
            'filters'     => $request->only(['search', 'estado']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'proveedor_id'           => 'required|exists:proveedores,id',
            'sucursal_id'            => 'required|exists:sucursales,id',
            'pagada'                 => 'nullable|boolean',
            'condicion_pago'         => 'nullable|in:cuenta_corriente,contado',
            'metodo_pago'            => 'nullable|in:Efectivo,Transferencia,Tarjeta',
            'observaciones'          => 'nullable|string',
            'items'                  => 'required|array|min:1',
            'items.*.libro_id'       => 'required|exists:libros,id',
            'items.*.cantidad'       => 'required|integer|min:1',
            'items.*.precio_unitario'=> 'required|numeric|min:0',
        ]);

        $sucursalRestringida = $request->user()->sucursalRestringidaId();
        if ($sucursalRestringida && (int) $request->sucursal_id !== $sucursalRestringida) {
            abort(403);
        }

        $total = collect($request->items)->sum(fn($i) => $i['cantidad'] * $i['precio_unitario']);
        $pagada = $request->boolean('pagada') || $request->input('condicion_pago') === 'contado';
        $condicionPago = $pagada ? 'contado' : 'cuenta_corriente';
        $metodoPago = $pagada ? ($request->input('metodo_pago') ?: 'Efectivo') : null;

        $orden = \DB::transaction(function () use ($request, $total, $condicionPago, $metodoPago, $pagada) {
            // Crear primero para obtener el ID autoincremental; luego generar el número
            $orden = OrdenCompra::create([
                'numero_orden'           => 'OC-TEMP',
                'proveedor_id'           => $request->proveedor_id,
                'sucursal_id'            => $request->sucursal_id,
                'estado'                 => 'confirmada',
                'condicion_pago'         => $condicionPago,
                'metodo_pago'            => $metodoPago,
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

            // Registrar movimiento financiero inmediatamente al crear la orden
            $proveedor = \App\Models\Proveedor::find($request->proveedor_id);
            if ($proveedor) {
                if ($pagada) {
                    \App\Models\Transaccion::create([
                        'tipo'                 => 'egreso',
                        'monto'                => $total,
                        'metodo_pago'          => $metodoPago ?: 'Efectivo',
                        'fecha'                => now(),
                        'sucursal_id'          => $request->sucursal_id,
                        'transaccionable_id'   => $proveedor->id,
                        'transaccionable_type' => \App\Models\Proveedor::class,
                        'descripcion'          => "Pago al contado por Orden de Compra {$orden->numero_orden}",
                        'user_id'              => \Auth::id(),
                    ]);
                } else {
                    $proveedor->increment('deuda_actual', $total);
                }
            }

            return $orden;
        });

        return redirect()->route('ordenes-compra.index')
            ->with('message', "Orden {$orden->numero_orden} creada.");
    }

    public function update(Request $request, OrdenCompra $ordenesCompra)
    {
        $sucursalRestringida = $request->user()->sucursalRestringidaId();
        if ($sucursalRestringida && $ordenesCompra->sucursal_id !== $sucursalRestringida) {
            abort(403);
        }

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

        if ($sucursalRestringida && (int) $request->sucursal_id !== $sucursalRestringida) {
            abort(403);
        }

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

    public function show(Request $request, OrdenCompra $ordenesCompra)
    {
        $sucursalRestringida = $request->user()->sucursalRestringidaId();
        if ($sucursalRestringida && $ordenesCompra->sucursal_id !== $sucursalRestringida) {
            abort(403);
        }

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

    public function recibir(Request $request, OrdenCompra $ordenesCompra)
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

                    // Notificar a los suscriptores activos de la serie en esta sucursal
                    if ($libro->master_id) {
                        $subscripciones = \App\Models\Suscripcion::where('libro_master_id', $libro->master_id)
                            ->where('sucursal_id', $fresh->sucursal_id)
                            ->where('estado', 'activa')
                            ->with('cliente.user')
                            ->get();

                        if ($subscripciones->isNotEmpty()) {
                            $clientes = $subscripciones->pluck('cliente')->filter();
                            if ($clientes->isNotEmpty()) {
                                auth()->user()->notify(new \App\Notifications\ClientesNotificadosIngresoNotification($libro, $fresh->sucursal_id, $clientes));
                            }
                        }
                    }
                }
            }

            $fresh->update(['estado' => 'recibida']);

            // Liberación automática de Preventas
            $ventasPreventa = \App\Models\Venta::where('estado', 'en_preventa')
                ->where('sucursal_id', $fresh->sucursal_id)
                ->with(['detalles.libro'])
                ->lockForUpdate()
                ->get();

            foreach ($ventasPreventa as $ventaPre) {
                $todasPreventasCubiertas = true;
                $detallesADescontar = [];
                
                foreach ($ventaPre->detalles as $detalle) {
                    if ($detalle->libro && $detalle->libro->permite_preventa) {
                        $stockLocal = \App\Models\Stock::where('libro_id', $detalle->libro_id)
                            ->where('sucursal_id', $fresh->sucursal_id)
                            ->lockForUpdate()
                            ->first();
                            
                        if (!$stockLocal || $stockLocal->cantidad_disponible < $detalle->cantidad) {
                            $todasPreventasCubiertas = false;
                            break;
                        }
                        
                        $detallesADescontar[] = [
                            'stock' => $stockLocal,
                            'cantidad' => $detalle->cantidad
                        ];
                    }
                }
                
                if ($todasPreventasCubiertas && !empty($detallesADescontar)) {
                    // Descontar stock
                    foreach ($detallesADescontar as $desc) {
                        $desc['stock']->decrement('cantidad_disponible', $desc['cantidad']);
                    }
                    
                    // Cambiar estado
                    $tieneTraslados = \App\Models\TransferenciaStock::where('venta_id', $ventaPre->id)->exists();
                    $nuevoEstado = $tieneTraslados ? 'esperando_traslado' : 'en_preparacion';
                    
                    if (!$tieneTraslados) {
                        if ($ventaPre->tipo_envio === 'retiro') {
                            $nuevoEstado = 'listo_para_retiro';
                        } elseif ($ventaPre->tipo_envio === 'acumulacion') {
                            $nuevoEstado = 'acumulado';
                        }
                    }
                    
                    $ventaPre->update(['estado' => $nuevoEstado]);
                    
                    if ($tieneTraslados) {
                        \App\Models\TransferenciaStock::where('venta_id', $ventaPre->id)
                            ->where('estado', 'pendiente')
                            ->update(['estado' => 'pendiente_envio']);
                            
                        $usuariosNotificar = \App\Models\User::where('activo', true)->get()->filter(fn($u) => $u->esAdmin() || $u->esGerente());
                        \Illuminate\Support\Facades\Notification::send($usuariosNotificar, new \App\Notifications\TrasladoPendienteVenta($ventaPre));
                    }
                }
            }
        });

        return redirect()->route('ordenes-compra.index')
            ->with('message', "Orden {$ordenesCompra->numero_orden} recibida. Stock y deuda actualizados.");
    }

    private function getDetalleDemandaLibro($libroId, $masterId, $numeroTomo, $sucursalId): array
    {
        if (!$sucursalId) {
            return [
                'reservas'           => 0,
                'suscriptores'       => 0,
                'total_comprometido' => 0,
                'demanda_detalle'    => [
                    'preventas'    => [],
                    'suscriptores' => [],
                ],
            ];
        }

        // 1. Preventas Web registradas para esta sucursal
        $preventas = \App\Models\VentaDetalle::where('libro_id', $libroId)
            ->whereHas('venta', function ($q) use ($sucursalId) {
                $q->where('estado', 'en_preventa')
                  ->where('sucursal_id', $sucursalId);
            })
            ->with(['venta.cliente.user:id,name,apellido,email'])
            ->get();

        $preventasCount = (int) $preventas->sum('cantidad');
        $preventasDetalle = $preventas->map(function ($vd) {
            $cliente = $vd->venta?->cliente;
            $user = $cliente?->user;
            $nombre = trim(($user?->name ?? '') . ' ' . ($user?->apellido ?? ''));
            return [
                'venta_id'       => $vd->venta_id,
                'codigo_venta'   => $vd->venta?->codigo_venta ?? "V-{$vd->venta_id}",
                'cliente_id'     => $cliente?->id,
                'cliente_nombre' => $nombre ?: 'Cliente Web',
                'cliente_email'  => $user?->email ?? '',
                'cantidad'       => (int) $vd->cantidad,
            ];
        })->values()->all();

        // IDs de clientes que ya compraron este tomo en preventa web
        $clienteIdsConPreventa = collect($preventasDetalle)->pluck('cliente_id')->filter()->unique()->all();

        // 2. Suscriptores Activos en esta Sucursal
        $suscriptoresCount = 0;
        $suscriptoresDetalle = [];

        if ($masterId) {
            $tomoNum = (int) preg_replace('/\D/', '', (string) $numeroTomo);
            $suscripcionesQuery = \App\Models\Suscripcion::where('libro_master_id', $masterId)
                ->where('sucursal_id', $sucursalId)
                ->where('estado', 'activa')
                ->whereNull('deleted_at')
                ->with(['cliente.user:id,name,apellido,email']);

            if ($tomoNum > 0) {
                $suscripcionesQuery->where(function ($q) use ($tomoNum) {
                    $q->whereNull('tomo_inicio')
                      ->orWhere('tomo_inicio', '<=', $tomoNum);
                });
            }

            $suscripciones = $suscripcionesQuery->get();

            foreach ($suscripciones as $sub) {
                // Si el suscriptor ya compró este tomo en preventa web, no se computa doble
                if ($sub->cliente_id && in_array($sub->cliente_id, $clienteIdsConPreventa)) {
                    continue;
                }

                $user = $sub->cliente?->user;
                $nombre = trim(($user?->name ?? '') . ' ' . ($user?->apellido ?? ''));

                $suscriptoresDetalle[] = [
                    'suscripcion_id' => $sub->id,
                    'cliente_id'     => $sub->cliente_id,
                    'cliente_nombre' => $nombre ?: 'Suscriptor',
                    'cliente_email'  => $user?->email ?? '',
                    'tomo_inicio'    => $sub->tomo_inicio ?: 1,
                ];
                $suscriptoresCount++;
            }
        }

        $totalComprometido = $preventasCount + $suscriptoresCount;

        return [
            'reservas'           => $preventasCount,
            'suscriptores'       => $suscriptoresCount,
            'total_comprometido' => $totalComprometido,
            'demanda_detalle'    => [
                'preventas'    => $preventasDetalle,
                'suscriptores' => $suscriptoresDetalle,
            ],
        ];
    }

    public function searchLibros(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim($request->get('q', ''));
        $proveedor_id = $request->get('proveedor_id');
        $sucursal_id = $request->get('sucursal_id');

        $query = \App\Models\Libro::whereHas('master')
            ->with(['master:id,titulo,proveedor_id', 'precioActual'])
            ->withSum(['stocks as stock_sucursal' => function($q) use ($sucursal_id) {
                if ($sucursal_id) {
                    $q->where('sucursal_id', $sucursal_id);
                }
            }], 'cantidad_disponible');

        if ($proveedor_id) {
            $query->whereHas('master', fn($query) => $query->where('proveedor_id', $proveedor_id));
        }

        if (strlen($q) > 0) {
            $cleanQ = preg_replace('/[-\/]/', ' ', $q);
            $rawWords = array_filter(explode(' ', mb_strtolower(trim($cleanQ))));
            $stopWords = ['tomo', 'tomos', 'vol', 'volumen', 'nro', 'num', 'numero'];
            $words = array_values(array_filter($rawWords, fn($w) => !in_array($w, $stopWords)));
            if (empty($words)) {
                $words = $rawWords;
            }

            foreach ($words as $w) {
                $like = '%' . $w . '%';
                $query->where(function($sub) use ($like) {
                    $sub->whereHas('master', fn($m) => $m->whereRaw('LOWER(titulo) LIKE ?', [$like]))
                        ->orWhereRaw('LOWER(numero_tomo) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(isbn) LIKE ?', [$like]);
                });
            }
        }

        $libros = $query->limit(50)
            ->get()
            ->map(function($l) use ($sucursal_id) {
                $demanda = $this->getDetalleDemandaLibro($l->id, $l->master_id, $l->numero_tomo, $sucursal_id);
                return [
                    'id'                 => $l->id,
                    'titulo'             => ($l->master?->titulo ?? 'Sin título') . ($l->numero_tomo ? ' - Tomo ' . $l->numero_tomo : ''),
                    'stock'              => (int) ($l->stock_sucursal ?? 0),
                    'reservas'           => $demanda['reservas'],
                    'suscriptores'       => $demanda['suscriptores'],
                    'total_comprometido' => $demanda['total_comprometido'],
                    'demanda_detalle'    => $demanda['demanda_detalle'],
                    'precio_costo'       => (float) ($l->precioActual?->precio_compra ?? 0),
                    'precio_unitario'    => (float) ($l->precioActual?->precio_compra ?? 0),
                ];
            });

        return response()->json($libros);
    }

    public function getPreventas(Request $request): \Illuminate\Http\JsonResponse
    {
        $proveedor_id = $request->get('proveedor_id');
        $sucursal_id = $request->get('sucursal_id');

        if (!$proveedor_id || !$sucursal_id) {
            return response()->json([]);
        }

        $libros = \App\Models\Libro::whereHas('master', fn($q) => $q->where('proveedor_id', $proveedor_id))
            ->where(function ($q) use ($sucursal_id) {
                $q->where(function ($qPrev) use ($sucursal_id) {
                    $qPrev->where('permite_preventa', true)
                        ->whereHas('ventaDetalles', function($vd) use ($sucursal_id) {
                            $vd->whereHas('venta', function($qVenta) use ($sucursal_id) {
                                $qVenta->where('estado', 'en_preventa')
                                       ->where('sucursal_id', $sucursal_id);
                            });
                        });
                })->orWhereHas('master.suscripciones', function ($qSub) use ($sucursal_id) {
                    $qSub->where('sucursal_id', $sucursal_id)
                         ->where('estado', 'activa')
                         ->whereNull('deleted_at');
                });
            })
            ->with(['master:id,titulo,proveedor_id', 'precioActual'])
            ->withSum(['stocks as stock_sucursal' => function($q) use ($sucursal_id) {
                $q->where('sucursal_id', $sucursal_id);
            }], 'cantidad_disponible')
            ->get();

        $result = [];
        foreach ($libros as $l) {
            $demanda = $this->getDetalleDemandaLibro($l->id, $l->master_id, $l->numero_tomo, $sucursal_id);
            if ($demanda['total_comprometido'] > 0) {
                $result[] = [
                    'id'                 => $l->id,
                    'titulo'             => ($l->master?->titulo ?? 'Sin título') . ($l->numero_tomo ? ' - Tomo ' . $l->numero_tomo : ''),
                    'stock'              => (int) ($l->stock_sucursal ?? 0),
                    'reservas'           => $demanda['reservas'],
                    'suscriptores'       => $demanda['suscriptores'],
                    'total_comprometido' => $demanda['total_comprometido'],
                    'demanda_detalle'    => $demanda['demanda_detalle'],
                    'precio_costo'       => (float) ($l->precioActual?->precio_compra ?? 0),
                    'precio_unitario'    => (float) ($l->precioActual?->precio_compra ?? 0),
                ];
            }
        }

        return response()->json($result);
    }

    public function destroy(Request $request, OrdenCompra $ordenesCompra)
    {
        $sucursalRestringida = $request->user()->sucursalRestringidaId();
        if ($sucursalRestringida && $ordenesCompra->sucursal_id !== $sucursalRestringida) {
            abort(403);
        }

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
