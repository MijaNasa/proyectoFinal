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
        $movimientos = MovimientoStock::with([
            'detalles.libro.master', 
            'origen', 
            'destino', 
            'user'
        ])->latest()->paginate(15);

        // Pre-cargar libros para el buscador del modal
        $libros = Libro::with(['master', 'master.autor', 'stocks:id,libro_id,sucursal_id,cantidad_disponible'])
            ->get()
            ->map(function ($l) {
                return [
                    'id' => $l->id,
                    'label' => $l->master->titulo . ' - Tomo ' . ($l->numero_tomo ?: 'Único') . ' - ' . ($l->isbn ?: 'S/I'),
                    'titulo' => $l->master->titulo . ' - Tomo ' . ($l->numero_tomo ?: 'Único'),
                    'isbn' => $l->isbn,
                    'autor' => $l->master->autor?->apellido ?? '',
                    'stocks' => $l->stocks->map(function($s) {
                        return [
                            'sucursal_id' => $s->sucursal_id,
                            'disponible' => $s->cantidad_disponible
                        ];
                    })
                ];
            });

        // Obtener la sucursal del empleado activo
        $empleado = auth()->user()->empleado;
        $sucursalId = $empleado ? $empleado->sucursal_id : Sucursal::first()->id;

        // Traslados por Ventas Pendientes (Solo los que tienen venta_id)
        $trasladosAEnviar = TransferenciaStock::with(['libro.master', 'sucursalDestino', 'venta'])
            ->whereNotNull('venta_id')
            ->where('sucursal_origen_id', $sucursalId)
            ->where('estado', 'pendiente_envio')
            ->get();

        $trasladosARecibir = TransferenciaStock::with(['libro.master', 'sucursalOrigen', 'venta'])
            ->whereNotNull('venta_id')
            ->where('sucursal_destino_id', $sucursalId)
            ->where('estado', 'en_transito')
            ->get();

        return inertia('Logistica/Index', [
            'movimientos' => $movimientos,
            'sucursales' => Sucursal::where('activo', true)->get(['id', 'nombre']),
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

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $movimiento = MovimientoStock::with('detalles')->findOrFail($id);

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

            // Descontar stock de la sucursal origen
            $stock = Stock::where('libro_id', $traslado->libro_id)
                ->where('sucursal_id', $traslado->sucursal_origen_id)
                ->lockForUpdate()
                ->first();

            if (!$stock || $stock->cantidad_disponible < $traslado->cantidad) {
                throw new \Exception('Stock insuficiente para realizar el envío.');
            }

            $stock->cantidad_disponible -= $traslado->cantidad;
            $stock->save();

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

            // Sumar stock en la sucursal destino
            $stock = Stock::firstOrCreate(
                ['libro_id' => $traslado->libro_id, 'sucursal_id' => $traslado->sucursal_destino_id],
                ['cantidad_disponible' => 0, 'cantidad_reservada' => 0]
            );

            $stock->cantidad_disponible += $traslado->cantidad;
            // Si la venta lo reserva, habría que manejarlo, pero por ahora lo sumamos a disponible y la venta luego lo consumirá o lo entregará.
            $stock->save();

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
}
