<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\MovimientoStock;
use App\Http\Requests\StoreStockRequest;
use App\Http\Requests\UpdateStockRequest;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $sucursalId = $request->user()->sucursalRestringidaId();
        // Si el empleado esta restringido a una sucursal, ignoramos el filtro que venga por query string.
        $sucursalFiltro = $sucursalId ?: $request->input('sucursal_id');

        $query = \App\Models\LibroMaster::query()
            ->with([
                'autor:id,nombre,apellido',
                'libros' => function($q) {
                    $q->orderBy('numero_tomo', 'asc');
                },
                'libros.stocks' => function($q) use ($sucursalFiltro) {
                    if ($sucursalFiltro) {
                        $q->where('sucursal_id', $sucursalFiltro);
                    }
                },
                'libros.stocks.sucursal:id,nombre'
            ]);



        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                  ->orWhereHas('autor', function($sq) use ($search) {
                      $sq->where('nombre', 'like', "%{$search}%")
                         ->orWhere('apellido', 'like', "%{$search}%");
                  })
                  ->orWhereHas('libros', function($sq) use ($search) {
                      $sq->where('isbn', 'like', "%{$search}%");
                  });
            });
        }

        $obras = $query->latest()->paginate(10)->withQueryString();

        return inertia('Stocks/Index', [
            'obras' => $obras,
            'sucursales' => \App\Models\Sucursal::where('activo', true)->when($sucursalId, fn($q) => $q->where('id', $sucursalId))->get(['id', 'nombre']),
            'libros' => \App\Models\Libro::with([
                    'master:id,titulo,autor_id',
                    'master.autor:id,apellido',
                ])
                ->has('master')
                ->select(['id', 'master_id', 'isbn'])
                ->get()
                ->map(function($l) {
                    $titulo = $l->master?->titulo ?? 'Sin título';
                    $autor = $l->master?->autor?->apellido ?? '';
                    return [
                        'id'     => $l->id,
                        'label'  => $titulo . ' (' . ($l->isbn ?: 'S/I') . ')',
                        'titulo' => $titulo,
                        'isbn'   => $l->isbn,
                        'autor'  => $autor,
                    ];
                }),
            'stocksExistentes' => \App\Models\Stock::select(['id', 'libro_id', 'sucursal_id', 'cantidad_disponible'])->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))->get(),
            'filters' => $request->only(['search', 'sucursal_id']),
        ]);
    }

    public function store(StoreStockRequest $request)
    {
        $user = $request->user();
        if (!$user->esAdmin() && $user->empleado?->sucursal_id !== (int) $request->sucursal_id) {
            abort(403);
        }

        $stock = Stock::create($request->safe()->except(['motivo']));

        $movimiento = MovimientoStock::create([
            'tipo' => 'ajuste',
            'sucursal_destino_id' => $stock->sucursal_id,
            'user_id' => auth()->id(),
            'motivo' => $request->motivo ?? 'Ingreso manual inicial desde panel de Stock'
        ]);

        $movimiento->detalles()->create([
            'libro_id' => $stock->libro_id,
            'cantidad' => $stock->cantidad_disponible,
            'costo_unitario' => 0
        ]);

        return redirect()->route('stocks.index')->with('message', 'Stock registrado con éxito');
    }

    public function update(UpdateStockRequest $request, Stock $stock)
    {
        $user = auth()->user();
        if (!$user->esAdmin() && $user->empleado?->sucursal_id !== $stock->sucursal_id) {
            abort(403);
        }

        $cantidadAnterior = $stock->cantidad_disponible;

        $stock->update($request->safe()->except(['tipo_movimiento_id', 'motivo']));

        $cantidadNueva = $stock->cantidad_disponible;
        $delta = $cantidadNueva - $cantidadAnterior;

        if ($delta !== 0) {
            $movimiento = MovimientoStock::create([
                'tipo' => 'ajuste',
                'sucursal_destino_id' => $stock->sucursal_id,
                'user_id' => auth()->id(),
                'motivo' => $request->motivo ?? 'Ajuste manual desde panel de Stock'
            ]);

            $movimiento->detalles()->create([
                'libro_id' => $stock->libro_id,
                'cantidad' => $delta,
                'costo_unitario' => 0
            ]);
        }

        return redirect()->route('stocks.index')->with('message', 'Stock actualizado con éxito');
    }

    public function destroy(Stock $stock)
    {
        $user = auth()->user();
        if (!$user->esAdmin() && $user->empleado?->sucursal_id !== $stock->sucursal_id) {
            abort(403);
        }

        $stock->delete();

        return redirect()->route('stocks.index')->with('message', 'Registro de stock eliminado con éxito');
    }
}
