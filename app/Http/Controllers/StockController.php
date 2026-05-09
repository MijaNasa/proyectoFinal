<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\MovimientoStock;
use App\Models\TipoMovimientoStock;
use App\Http\Requests\StoreStockRequest;
use App\Http\Requests\UpdateStockRequest;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $query = Stock::query()->with(['libro.master.autor', 'sucursal']);

        if ($request->has('sucursal_id')) {
            $query->where('sucursal_id', $request->sucursal_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('libro', function($q) use ($search) {
                $q->where('isbn', 'like', '%' . $search . '%')
                  ->orWhereHas('master', function($sq) use ($search) {
                      $sq->where('titulo', 'like', '%' . $search . '%');
                  });
            });
        }

        $stocks = $query->latest()->paginate(10)->withQueryString();

        $tiposMovimiento = TipoMovimientoStock::where('activo', true)->get(['id', 'codigo', 'nombre']);

        return inertia('Stocks/Index', [
            'stocks' => $stocks,
            'sucursales' => \App\Models\Sucursal::where('activo', true)->get(['id', 'nombre']),
            'libros' => \App\Models\Libro::with('master.autor')->get()->map(function($l) {
                return [
                    'id'     => $l->id,
                    'label'  => $l->master->titulo . ' (' . ($l->isbn ?: 'S/I') . ')',
                    'titulo' => $l->master->titulo,
                    'isbn'   => $l->isbn,
                    'autor'  => $l->master->autor?->apellido ?? '',
                ];
            }),
            'tiposMovimiento' => $tiposMovimiento,
            'stocksExistentes' => \App\Models\Stock::select('libro_id', 'sucursal_id', 'cantidad_disponible')->get(),
            'filters' => $request->only(['search', 'sucursal_id']),
        ]);
    }

    public function store(StoreStockRequest $request)
    {
        $stock = Stock::create($request->safe()->except(['motivo']));

        $tipoIngreso = TipoMovimientoStock::where('codigo', 'INGRESO_MANUAL')->first();

        if ($tipoIngreso) {
            MovimientoStock::create([
                'stock_id'          => $stock->id,
                'tipo_movimiento_id' => $tipoIngreso->id,
                'cantidad'          => $stock->cantidad_disponible,
                'cantidad_anterior' => 0,
                'cantidad_nueva'    => $stock->cantidad_disponible,
                'motivo'            => $request->motivo,
                'user_id'           => auth()->id(),
                'fecha_movimiento'  => now(),
            ]);
        }

        return redirect()->route('stocks.index')->with('message', 'Stock registrado con éxito');
    }

    public function update(UpdateStockRequest $request, Stock $stock)
    {
        $cantidadAnterior = $stock->cantidad_disponible;

        $stock->update($request->safe()->except(['tipo_movimiento_id', 'motivo']));

        $cantidadNueva = $stock->fresh()->cantidad_disponible;
        $delta = abs($cantidadNueva - $cantidadAnterior);

        if ($delta > 0) {
            MovimientoStock::create([
                'stock_id'          => $stock->id,
                'tipo_movimiento_id' => $request->tipo_movimiento_id,
                'cantidad'          => $delta,
                'cantidad_anterior' => $cantidadAnterior,
                'cantidad_nueva'    => $cantidadNueva,
                'motivo'            => $request->motivo,
                'user_id'           => auth()->id(),
                'fecha_movimiento'  => now(),
            ]);
        }

        return redirect()->route('stocks.index')->with('message', 'Stock actualizado con éxito');
    }

    public function destroy(Stock $stock)
    {
        $stock->delete();

        return redirect()->route('stocks.index')->with('message', 'Registro de stock eliminado con éxito');
    }
}
