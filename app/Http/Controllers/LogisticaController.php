<?php

namespace App\Http\Controllers;

use App\Models\Libro;
use App\Models\Sucursal;
use App\Models\Stock;
use App\Models\MovimientoStock;
use App\Models\TransferenciaStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogisticaController extends Controller
{
    public function index(Request $request)
    {
        $sucursalId = $request->user()->sucursalRestringidaId();

        $movimientos = MovimientoStock::with([
            'detalles.libro.master',
            'origen',
            'destino',
            'user'
        ])
            ->when($sucursalId, fn($q) => $q->where(function ($sq) use ($sucursalId) {
                $sq->where('sucursal_origen_id', $sucursalId)
                   ->orWhere('sucursal_destino_id', $sucursalId);
            }))
            ->latest()->paginate(15);

        // Pre-cargar libros para el buscador del modal
        $libros = Libro::with(['master', 'master.autor', 'stocks:id,libro_id,sucursal_id,cantidad_disponible'])
            ->has('master')
            ->get()
            ->map(function ($l) {
                $titulo = ($l->master?->titulo ?? 'Sin título') . ' - Tomo ' . ($l->numero_tomo ?: 'Único');
                return [
                    'id' => $l->id,
                    'label' => $titulo . ' - ' . ($l->isbn ?: 'S/I'),
                    'titulo' => $titulo,
                    'isbn' => $l->isbn,
                    'autor' => $l->master?->autor?->apellido ?? '',
                    'stocks' => $l->stocks->map(function($s) {
                        return [
                            'sucursal_id' => $s->sucursal_id,
                            'disponible' => $s->cantidad_disponible
                        ];
                    })
                ];
            });

        $esAdmin = is_null($sucursalId);

        // Traslados por Ventas Pendientes (Solo los que tienen venta_id)
        $trasladosAEnviar = TransferenciaStock::with(['libro.master', 'sucursalDestino', 'venta'])
            ->whereNotNull('venta_id')
            ->whereHas('venta', function($q) {
                $q->where('estado', '!=', 'cancelado');
            })
            ->when(!$esAdmin, function($q) use ($sucursalId) {
                return $q->where('sucursal_origen_id', $sucursalId);
            })
            ->where('estado', 'pendiente_envio')
            ->get();

        $trasladosARecibir = TransferenciaStock::with(['libro.master', 'sucursalOrigen', 'venta'])
            ->whereNotNull('venta_id')
            ->whereHas('venta', function($q) {
                $q->where('estado', '!=', 'cancelado');
            })
            ->when(!$esAdmin, function($q) use ($sucursalId) {
                return $q->where('sucursal_destino_id', $sucursalId);
            })
            ->where('estado', 'en_transito')
            ->get();

        return inertia('Logistica/Index', [
            'movimientos' => $movimientos,
            'sucursales' => Sucursal::where('activo', true)->when($sucursalId, fn($q) => $q->where('id', $sucursalId))->get(['id', 'nombre']),
            'libros' => $libros,
            'trasladosAEnviar' => $trasladosAEnviar,
            'trasladosARecibir' => $trasladosARecibir,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'tipo' => 'required|in:ingreso_proveedor,ingreso_manual,egreso_manual,ajuste',
            'sucursal_destino_id' => 'required_if:tipo,ingreso_proveedor,ingreso_manual,egreso_manual,ajuste|nullable|exists:sucursales,id',
            'motivo' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.libro_id' => 'required|exists:libros,id',
            'items.*.cantidad' => 'required|integer',
            'items.*.costo_unitario' => 'required_if:tipo,ingreso_proveedor|nullable|numeric|min:0',
        ]);

        $sucursalRestringida = $request->user()->sucursalRestringidaId();
        if ($sucursalRestringida && (int) $request->sucursal_destino_id !== $sucursalRestringida) {
            abort(403);
        }

        try {
            DB::beginTransaction();

            $tipo = $request->tipo;
            $sucursalId = $request->sucursal_destino_id;

            // 1. Crear Cabecera
            $movimiento = MovimientoStock::create([
                'tipo' => $tipo,
                'sucursal_origen_id' => ($tipo === 'egreso_manual') ? $sucursalId : null,
                'sucursal_destino_id' => ($tipo === 'egreso_manual') ? null : $sucursalId,
                'user_id' => auth()->id(),
                'motivo' => $request->motivo
            ]);

            // 2. Procesar Detalles y Actualizar Stocks
            foreach ($request->items as $item) {
                $libro = Libro::findOrFail($item['libro_id']);
                $cantidad = (int) $item['cantidad'];
                $costo_unitario = isset($item['costo_unitario']) ? (float) $item['costo_unitario'] : null;

                if ($tipo === 'ajuste' && $cantidad === 0) {
                    throw new \Exception('La cantidad en un ajuste no puede ser 0 para el libro ' . $libro->master->titulo);
                }
                if ($tipo !== 'ajuste' && $cantidad <= 0) {
                    throw new \Exception('La cantidad debe ser mayor a 0 para el libro ' . $libro->master->titulo);
                }

                // Sumar/Restar al destino/origen
                $stock = Stock::firstOrCreate(
                    ['libro_id' => $libro->id, 'sucursal_id' => $sucursalId],
                    ['cantidad_disponible' => 0, 'cantidad_reservada' => 0]
                );

                if ($tipo === 'egreso_manual') {
                    if ($stock->cantidad_disponible < $cantidad) {
                        throw new \Exception('Stock insuficiente para el egreso manual del libro ' . $libro->master->titulo);
                    }
                    $stock->cantidad_disponible -= $cantidad;
                } else if ($tipo === 'ingreso_manual' || $tipo === 'ingreso_proveedor' || $tipo === 'ajuste') {
                    $stock->cantidad_disponible += $cantidad;
                    if ($stock->cantidad_disponible < 0) {
                        throw new \Exception('El ajuste resultaría en stock negativo para ' . $libro->master->titulo);
                    }
                }
                
                $stock->save();

                // Recalcular PPP si es Ingreso por Proveedor
                if ($tipo === 'ingreso_proveedor' && $costo_unitario !== null) {
                    $libro->recalcularCostoPPP($costo_unitario, $cantidad);
                }

                // Crear Detalle
                $movimiento->detalles()->create([
                    'libro_id' => $libro->id,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costo_unitario,
                ]);

                // Notificar suscripciones si es Ingreso por Proveedor o Ingreso Manual
                if (in_array($tipo, ['ingreso_proveedor', 'ingreso_manual'])) {
                    $suscripciones = \App\Models\Suscripcion::where('libro_master_id', $libro->master_id)
                        ->where('estado', 'activa')
                        ->where('sucursal_id', $sucursalId)
                        ->with('cliente.user')
                        ->get();

                    if ($suscripciones->isNotEmpty()) {
                        $clientes = $suscripciones->map(fn($s) => $s->cliente);

                        foreach ($clientes as $cliente) {
                            $cliente->user?->notify(new \App\Notifications\TomoIngresadoNotification($cliente, $libro, $sucursalId));
                        }

                        $request->user()->notify(new \App\Notifications\ClientesNotificadosIngresoNotification($libro, $sucursalId, $clientes));
                    }
                }
            }

            DB::commit();

            return redirect()->back()->with('message', 'Movimiento registrado correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy(Request $request, $id)
    {
        $movimiento = MovimientoStock::with('detalles')->findOrFail($id);
        $sucursalRestringida = $request->user()->sucursalRestringidaId();
        if ($sucursalRestringida && $sucursalRestringida !== $movimiento->sucursal_origen_id && $sucursalRestringida !== $movimiento->sucursal_destino_id) {
            abort(403);
        }

        try {
            DB::beginTransaction();

            // Cannot undo a transferencia sale directly from here if it has complex logic,
            // but we can undo manual ingress/egress.
            if ($movimiento->tipo === 'TRANSFERENCIA_SALIDA' || $movimiento->tipo === 'TRANSFERENCIA_ENTRADA') {
                throw new \Exception('No se puede deshacer un movimiento de logística automático por ventas desde aquí.');
            }

            foreach ($movimiento->detalles as $detalle) {
                $sucursalId = $movimiento->tipo === 'egreso_manual' ? $movimiento->sucursal_origen_id : $movimiento->sucursal_destino_id;
                
                $stock = Stock::firstOrCreate(
                    ['libro_id' => $detalle->libro_id, 'sucursal_id' => $sucursalId],
                    ['cantidad_disponible' => 0, 'cantidad_reservada' => 0]
                );

                if ($movimiento->tipo === 'egreso_manual') {
                    // Reverse egress = Add stock
                    $stock->cantidad_disponible += $detalle->cantidad;
                } else if ($movimiento->tipo === 'ingreso_manual' || $movimiento->tipo === 'ingreso_proveedor' || $movimiento->tipo === 'ajuste') {
                    // Reverse ingress/adjustment = Subtract stock
                    $stock->cantidad_disponible -= $detalle->cantidad;
                    if ($stock->cantidad_disponible < 0) {
                        throw new \Exception('Deshacer este movimiento resultaría en stock negativo para el libro.');
                    }
                }
                
                $stock->save();
                
                // Si fue ingreso por proveedor, deshacer PPP no es trivial, lo dejamos como está 
                // ya que requiere historial de costos previos que no guardamos.
            }

            $movimiento->detalles()->delete();
            $movimiento->delete();

            DB::commit();

            return redirect()->back()->with('message', 'Movimiento deshecho correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function registrarEnvioVenta(TransferenciaStock $traslado)
    {
        if ($traslado->estado !== 'pendiente_envio') {
            return back()->withErrors(['error' => 'Este traslado ya fue procesado o cancelado.']);
        }

        try {
            DB::beginTransaction();

            $traslado->update(['estado' => 'en_transito']);

            // No descontamos stock porque ya fue descontado en la creación de la venta online (CheckoutController)

            // Registrar movimiento de egreso
            $movimiento = MovimientoStock::create([
                'tipo' => 'TRANSFERENCIA_SALIDA',
                'sucursal_origen_id' => $traslado->sucursal_origen_id,
                'sucursal_destino_id' => $traslado->sucursal_destino_id,
                'user_id' => auth()->id(),
                'motivo' => 'Envío por Venta #' . $traslado->venta_id
            ]);

            $movimiento->detalles()->create([
                'libro_id' => $traslado->libro_id,
                'cantidad' => $traslado->cantidad,
            ]);

            DB::commit();
            return back()->with('message', 'Envío registrado correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function registrarRecepcionVenta(TransferenciaStock $traslado)
    {
        if ($traslado->estado !== 'en_transito') {
            return back()->withErrors(['error' => 'Este traslado no está en tránsito.']);
        }

        try {
            DB::beginTransaction();

            $traslado->update(['estado' => 'completado']);

            if ($traslado->venta_id) {
                $pendingTransfers = \App\Models\TransferenciaStock::where('venta_id', $traslado->venta_id)
                    ->where('estado', '!=', 'completado')
                    ->count();

                if ($pendingTransfers === 0) {
                    $venta = \App\Models\Venta::find($traslado->venta_id);
                    if ($venta && $venta->estado === 'esperando_traslado') {
                        if ($venta->tipo_envio === 'domicilio') {
                            $venta->update(['estado' => 'en_preparacion']);
                        } else {
                            $venta->update(['estado' => 'listo_para_retiro']);
                        }
                    }
                }
            }

            // No sumamos stock a la sucursal de destino porque el stock ya fue reservado/descontado y el libro es para entregar directamente al cliente.

            // Registrar movimiento de ingreso
            $movimiento = MovimientoStock::create([
                'tipo' => 'TRANSFERENCIA_ENTRADA',
                'sucursal_origen_id' => $traslado->sucursal_origen_id,
                'sucursal_destino_id' => $traslado->sucursal_destino_id,
                'user_id' => auth()->id(),
                'motivo' => 'Recepción por Venta #' . $traslado->venta_id
            ]);

            $movimiento->detalles()->create([
                'libro_id' => $traslado->libro_id,
                'cantidad' => $traslado->cantidad,
            ]);

            DB::commit();
            return back()->with('message', 'Recepción registrada correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error: ' . $e->getMessage()]);
        }
    }
    public function updateCostoDetalle(Request $request, $id)
    {
        $request->validate([
            'costo_unitario' => 'required|numeric|min:0',
            'actualizar_catalogo' => 'boolean'
        ]);

        try {
            DB::beginTransaction();

            $detalle = \App\Models\MovimientoStockDetalle::with('libro')->findOrFail($id);
            $movimiento = \App\Models\MovimientoStock::findOrFail($detalle->movimiento_id);
            
            if ($movimiento->tipo !== 'ingreso_proveedor') {
                throw new \Exception('Solo se pueden editar costos de ingresos por proveedor.');
            }

            $detalle->costo_unitario = $request->costo_unitario;
            $detalle->save();

            if ($request->actualizar_catalogo) {
                $detalle->libro->recalcularCostoPPP($request->costo_unitario);
            }

            DB::commit();
            return back()->with('message', 'Costo actualizado correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error: ' . $e->getMessage()]);
        }
    }
}
