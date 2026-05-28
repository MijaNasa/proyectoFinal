<?php

namespace App\Http\Controllers;

use App\Models\TransferenciaStock;
use App\Models\Stock;
use App\Models\MovimientoStock;
use App\Models\TipoMovimientoStock;
use App\Models\Sucursal;
use App\Models\Libro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferenciaStockController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $search     = $request->get('search', '');
        $sucursalId = $request->get('sucursal_id', '');

        $query = TransferenciaStock::with([
            'libro.master:id,titulo',
            'sucursalOrigen:id,nombre',
            'sucursalDestino:id,nombre',
            'user:id,name,apellido',
        ])->latest();

        if ($search) {
            $query->whereHas('libro.master', fn($q) => $q->where('titulo', 'like', "%{$search}%"))
                  ->orWhereHas('libro', fn($q) => $q->where('isbn', 'like', "%{$search}%"));
        }

        if ($sucursalId) {
            $query->where(fn($q) => $q
                ->where('sucursal_origen_id', $sucursalId)
                ->orWhere('sucursal_destino_id', $sucursalId)
            );
        }

        $transferencias = $query->paginate(20)->withQueryString();

        // Libros con stock disponible en al menos una sucursal
        $librosConStock = Libro::with([
                'master:id,titulo',
                'stocks' => fn($q) => $q->where('cantidad_disponible', '>', 0)
                                        ->select('libro_id', 'sucursal_id', 'cantidad_disponible'),
            ])
            ->whereHas('stocks', fn($q) => $q->where('cantidad_disponible', '>', 0))
            ->select('id', 'master_id', 'isbn')
            ->get()
            ->map(fn($l) => [
                'id'     => $l->id,
                'titulo' => $l->master->titulo,
                'isbn'   => $l->isbn,
                'stocks' => $l->stocks->map(fn($s) => [
                    'sucursal_id'        => $s->sucursal_id,
                    'cantidad_disponible'=> $s->cantidad_disponible,
                ])->values(),
            ])
            ->values();

        return inertia('Stocks/Transferencias', [
            'transferencias' => $transferencias,
            'sucursales'     => Sucursal::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'librosConStock' => $librosConStock,
            'filters'        => compact('search', 'sucursalId'),
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'libro_id'            => 'required|exists:libros,id',
            'sucursal_origen_id'  => 'required|exists:sucursales,id',
            'sucursal_destino_id' => 'required|exists:sucursales,id|different:sucursal_origen_id',
            'cantidad'            => 'required|integer|min:1',
            'motivo'              => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data) {
            // lockForUpdate evita race condition: dos requests simultáneos no pueden
            // pasar el chequeo de stock al mismo tiempo
            $stockOrigen = Stock::where('libro_id', $data['libro_id'])
                ->where('sucursal_id', $data['sucursal_origen_id'])
                ->lockForUpdate()
                ->first();

            if (!$stockOrigen || $stockOrigen->cantidad_disponible < $data['cantidad']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'cantidad' => 'Stock insuficiente en la sucursal origen. Disponible: ' . ($stockOrigen?->cantidad_disponible ?? 0),
                ]);
            }

            $tipoSalida  = TipoMovimientoStock::firstOrCreate(
                ['codigo' => 'TRANSFERENCIA_SALIDA'],
                ['nombre' => 'Transferencia - Salida', 'afecta_stock' => true, 'activo' => true]
            );
            $tipoEntrada = TipoMovimientoStock::firstOrCreate(
                ['codigo' => 'TRANSFERENCIA_ENTRADA'],
                ['nombre' => 'Transferencia - Entrada', 'afecta_stock' => true, 'activo' => true]
            );

            $transferencia = TransferenciaStock::create(array_merge($data, [
                'user_id' => auth()->id(),
                'fecha'   => now()->toDateString(),
            ]));

            $anteriorOrigen = $stockOrigen->cantidad_disponible;
            $stockOrigen->decrement('cantidad_disponible', $data['cantidad']);

            MovimientoStock::create([
                'stock_id'           => $stockOrigen->id,
                'tipo_movimiento_id' => $tipoSalida->id,
                'cantidad'           => $data['cantidad'],
                'cantidad_anterior'  => $anteriorOrigen,
                'cantidad_nueva'     => $anteriorOrigen - $data['cantidad'],
                'motivo'             => $data['motivo'] ?? 'Transferencia a ' . Sucursal::find($data['sucursal_destino_id'])->nombre,
                'referencia_id'      => $transferencia->id,
                'referencia_type'    => TransferenciaStock::class,
                'user_id'            => auth()->id(),
                'fecha_movimiento'   => now(),
            ]);

            $stockDestino = Stock::firstOrCreate(
                ['libro_id' => $data['libro_id'], 'sucursal_id' => $data['sucursal_destino_id']],
                ['cantidad_disponible' => 0, 'cantidad_reservada' => 0, 'activo' => true]
            );

            $anteriorDestino = $stockDestino->cantidad_disponible;
            $stockDestino->increment('cantidad_disponible', $data['cantidad']);

            MovimientoStock::create([
                'stock_id'           => $stockDestino->id,
                'tipo_movimiento_id' => $tipoEntrada->id,
                'cantidad'           => $data['cantidad'],
                'cantidad_anterior'  => $anteriorDestino,
                'cantidad_nueva'     => $anteriorDestino + $data['cantidad'],
                'motivo'             => $data['motivo'] ?? 'Transferencia desde ' . Sucursal::find($data['sucursal_origen_id'])->nombre,
                'referencia_id'      => $transferencia->id,
                'referencia_type'    => TransferenciaStock::class,
                'user_id'            => auth()->id(),
                'fecha_movimiento'   => now(),
            ]);
        });

        return back()->with('message', 'Transferencia realizada correctamente');
    }
}
