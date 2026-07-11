<?php

namespace App\Http\Controllers;

use App\Models\Libro;
use App\Models\Sucursal;
use App\Models\Stock;
use App\Models\MovimientoStock;
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

        return inertia('Logistica/Index', [
            'movimientos' => $movimientos,
            'sucursales' => Sucursal::where('activo', true)->get(['id', 'nombre']),
            'libros' => $libros,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'tipo' => 'required|in:ingreso_proveedor,transferencia,ajuste',
            'sucursal_destino_id' => 'required_if:tipo,ingreso_proveedor,ajuste,transferencia|nullable|exists:sucursales,id',
            'sucursal_origen_id' => 'required_if:tipo,transferencia|nullable|exists:sucursales,id',
            'motivo' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.libro_id' => 'required|exists:libros,id',
            'items.*.cantidad' => 'required|integer',
            'items.*.costo_unitario' => 'required_if:tipo,ingreso_proveedor|nullable|numeric|min:0',
        ], [
            'sucursal_origen_id.required_if' => 'La sucursal de origen es obligatoria para transferencias.',
            'sucursal_destino_id.required_if' => 'La sucursal de destino es obligatoria.',
            'items.*.costo_unitario.required_if' => 'Debe ingresar el costo unitario para calcular el PPP en todos los libros.',
        ]);

        if ($request->tipo === 'transferencia' && $request->sucursal_origen_id == $request->sucursal_destino_id) {
            return back()->withErrors(['sucursal_destino_id' => 'La sucursal de destino debe ser diferente a la de origen.']);
        }

        try {
            DB::beginTransaction();

            $tipo = $request->tipo;

            // 1. Crear Cabecera
            $movimiento = MovimientoStock::create([
                'tipo' => $tipo,
                'sucursal_origen_id' => $request->sucursal_origen_id,
                'sucursal_destino_id' => $request->sucursal_destino_id,
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

                // Transferencia: Restar del origen
                if ($tipo === 'transferencia') {
                    $stockOrigen = Stock::where('libro_id', $libro->id)
                        ->where('sucursal_id', $request->sucursal_origen_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$stockOrigen || $stockOrigen->cantidad_disponible < $cantidad) {
                        throw new \Exception('Stock insuficiente en la sucursal de origen para el libro ' . $libro->master->titulo);
                    }

                    $stockOrigen->cantidad_disponible -= $cantidad;
                    $stockOrigen->save();
                }

                // Sumar/Ajustar al destino
                if (in_array($tipo, ['ingreso_proveedor', 'transferencia', 'ajuste'])) {
                    $stockDestino = Stock::firstOrCreate(
                        ['libro_id' => $libro->id, 'sucursal_id' => $request->sucursal_destino_id],
                        ['cantidad_disponible' => 0, 'cantidad_reservada' => 0]
                    );

                    $stockDestino->cantidad_disponible += $cantidad;

                    if ($stockDestino->cantidad_disponible < 0) {
                        throw new \Exception('El ajuste resultaría en stock negativo para ' . $libro->master->titulo);
                    }
                    $stockDestino->save();
                }

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

                // Notificar suscripciones si es Ingreso por Proveedor
                if ($tipo === 'ingreso_proveedor') {
                    $suscripciones = \App\Models\Suscripcion::where('libro_master_id', $libro->master_id)
                        ->where('estado', 'activa')
                        ->where(function($q) use ($request) {
                            $q->whereNull('sucursal_id')
                              ->orWhere('sucursal_id', $request->sucursal_destino_id);
                        })
                        ->with('cliente.user')
                        ->get();

                    foreach ($suscripciones as $susc) {
                        // Se avisa al cliente suscripto (no al empleado que carga el stock).
                        $susc->cliente->user->notify(new \App\Notifications\TomoIngresadoNotification($susc->cliente, $libro, $request->sucursal_destino_id));
                    }
                }
            }

            DB::commit();

            return redirect()->back()->with('message', 'Movimiento registrado correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
