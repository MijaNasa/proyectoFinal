<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Http\Requests\StoreStockRequest;
use App\Http\Requests\UpdateStockRequest;
use Illuminate\Http\Request;

class StockController extends Controller
{
    /**
     * Display a listing of the resource.
     */
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

        return inertia('Stocks/Index', [
            'stocks' => $stocks,
            'sucursales' => \App\Models\Sucursal::where('activo', true)->get(['id', 'nombre']),
            'libros' => \App\Models\Libro::with('master')->get()->map(function($l) {
                return [
                    'id' => $l->id,
                    'label' => $l->master->titulo . ' (' . ($l->isbn ?: 'S/I') . ')'
                ];
            }),
            'filters' => $request->only(['search', 'sucursal_id'])
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStockRequest $request)
    {
        Stock::create($request->validated());

        return redirect()->route('stocks.index')
            ->with('message', 'Stock registrado con éxito');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStockRequest $request, Stock $stock)
    {
        $stock->update($request->validated());

        return redirect()->route('stocks.index')
            ->with('message', 'Stock actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Stock $stock)
    {
        $stock->delete();

        return redirect()->route('stocks.index')
            ->with('message', 'Registro de stock eliminado con éxito');
    }
}
