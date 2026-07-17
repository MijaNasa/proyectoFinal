<?php

namespace App\Http\Controllers;

use App\Models\TransferenciaStock;
use App\Models\Venta;
use App\Models\Stock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class TransferenciaStockController extends Controller
{
    public function index(Request $request)
    {
        $query = TransferenciaStock::with(['libro', 'sucursalOrigen', 'sucursalDestino', 'venta.cliente', 'user'])
            ->orderByRaw("CASE WHEN estado = 'pendiente' THEN 1 ELSE 2 END")
            ->orderBy('created_at', 'desc');

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        $transferencias = $query->paginate(20);

        return Inertia::render('Transferencias/Index', [
            'transferencias' => $transferencias
        ]);
    }

    public function recibir(TransferenciaStock $transferencia)
    {
        if ($transferencia->estado === 'recibido') {
            return back()->with('error', 'El traslado ya fue marcado como recibido.');
        }

        DB::transaction(function () use ($transferencia) {
            // Marcar traslado como recibido
            $transferencia->update(['estado' => 'recibido']);

            // Ingresar el stock a la sucursal de destino
            $stockDestino = Stock::firstOrCreate(
                [
                    'libro_id' => $transferencia->libro_id,
                    'sucursal_id' => $transferencia->sucursal_destino_id,
                ],
                [
                    'cantidad_disponible' => 0,
                    'cantidad_total' => 0,
                ]
            );

            // Si es un traslado manual (sin venta asociada), aumentamos el stock disponible en destino.
            // Si es por una venta web, el stock NO se aumenta porque ya está comprometido para ese cliente.
            if (is_null($transferencia->venta_id)) {
                $stockDestino->cantidad_disponible += $transferencia->cantidad;
                $stockDestino->save();
            }

            // Chequear si la Venta completó todos sus traslados
            if ($transferencia->venta_id) {
                $venta = Venta::find($transferencia->venta_id);
                $trasladosPendientes = TransferenciaStock::where('venta_id', $venta->id)
                    ->where('estado', 'pendiente')
                    ->count();

                if ($trasladosPendientes === 0) {
                    $venta->update(['estado' => 'listo_para_retirar']);
                }
            }
        });

        return back()->with('success', 'Traslado recibido correctamente.');
    }
}
