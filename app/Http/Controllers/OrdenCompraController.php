<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use Illuminate\Http\Request;

class OrdenCompraController extends Controller
{
    public function index(Request $request)
    {
        $query = OrdenCompra::with(['proveedor', 'sucursal', 'user:id,name,apellido'])
            ->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero_orden', 'like', "%{$search}%")
                  ->orWhereHas('proveedor', fn($q2) => $q2->where('nombre', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $ordenes = $query->paginate(15)->withQueryString();

        $stats = [
            'total'      => OrdenCompra::count(),
            'borradores' => OrdenCompra::where('estado', 'borrador')->count(),
            'confirmadas'=> OrdenCompra::where('estado', 'confirmada')->count(),
            'recibidas'  => OrdenCompra::where('estado', 'recibida')->count(),
        ];

        return inertia('OrdenesCompra/Index', [
            'ordenes'     => $ordenes,
            'proveedores' => \App\Models\Proveedor::orderBy('nombre')->get(['id', 'nombre']),
            'sucursales'  => \App\Models\Sucursal::where('activo', true)->get(['id', 'nombre']),
            'stats'       => $stats,
            'filters'     => $request->only(['search', 'estado']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'proveedor_id'           => 'required|exists:proveedores,id',
            'sucursal_id'            => 'required|exists:sucursales,id',
            'fecha_entrega_estimada' => 'nullable|date',
            'observaciones'          => 'nullable|string',
            'items'                  => 'required|array|min:1',
            'items.*.libro_id'       => 'required|exists:libros,id',
            'items.*.cantidad'       => 'required|integer|min:1',
            'items.*.precio_unitario'=> 'required|numeric|min:0',
        ]);

        $numero = 'OC-' . str_pad(OrdenCompra::withTrashed()->count() + 1, 6, '0', STR_PAD_LEFT);

        $total = collect($request->items)->sum(fn($i) => $i['cantidad'] * $i['precio_unitario']);

        $orden = OrdenCompra::create([
            'numero_orden'           => $numero,
            'proveedor_id'           => $request->proveedor_id,
            'sucursal_id'            => $request->sucursal_id,
            'estado'                 => 'borrador',
            'fecha'                  => now()->toDateString(),
            'fecha_entrega_estimada' => $request->fecha_entrega_estimada,
            'total'                  => $total,
            'observaciones'          => $request->observaciones,
            'user_id'                => \Auth::id(),
        ]);

        foreach ($request->items as $item) {
            $orden->items()->create([
                'libro_id'        => $item['libro_id'],
                'cantidad'        => $item['cantidad'],
                'precio_unitario' => $item['precio_unitario'],
                'subtotal'        => $item['cantidad'] * $item['precio_unitario'],
            ]);
        }

        return redirect()->route('ordenes-compra.index')
            ->with('message', "Orden {$numero} creada.");
    }

    public function show(OrdenCompra $ordenesCompra)
    {
        $ordenesCompra->load([
            'proveedor',
            'sucursal',
            'user:id,name,apellido',
            'items.libro.master:id,titulo',
            'items.libro:id,master_id,isbn',
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

    public function recibir(OrdenCompra $ordenesCompra)
    {
        $user = auth()->user();
        if (!$user->esAdmin() && $ordenesCompra->sucursal_id !== $user->empleado?->sucursal_id) {
            abort(403);
        }

        if ($ordenesCompra->estado !== 'confirmada') {
            return back()->withErrors(['estado' => 'Solo se puede recibir una orden confirmada.']);
        }

        $ordenesCompra->load('items');

        \DB::transaction(function () use ($ordenesCompra) {
            foreach ($ordenesCompra->items as $item) {
                \App\Models\Stock::firstOrCreate(
                    ['libro_id' => $item->libro_id, 'sucursal_id' => $ordenesCompra->sucursal_id],
                    ['cantidad_disponible' => 0]
                )->increment('cantidad_disponible', $item->cantidad);
            }

            \App\Models\Proveedor::find($ordenesCompra->proveedor_id)
                ->increment('deuda_actual', $ordenesCompra->total);

            $ordenesCompra->update(['estado' => 'recibida']);
        });

        return redirect()->route('ordenes-compra.index')
            ->with('message', "Orden {$ordenesCompra->numero_orden} recibida. Stock y deuda actualizados.");
    }

    public function searchLibros(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim($request->get('q', ''));

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $libros = \App\Models\Libro::with('master:id,titulo')
            ->where(fn($query) => $query
                ->whereHas('master', fn($q2) => $q2->where('titulo', 'like', "%{$q}%"))
                ->orWhere('isbn', 'like', "%{$q}%")
            )
            ->select('id', 'master_id', 'isbn')
            ->limit(20)
            ->get()
            ->map(fn($l) => [
                'id'    => $l->id,
                'titulo'=> $l->master->titulo,
                'isbn'  => $l->isbn,
            ]);

        return response()->json($libros);
    }

    public function destroy(OrdenCompra $ordenesCompra)
    {
        if (!in_array($ordenesCompra->estado, ['borrador', 'confirmada'])) {
            return back()->withErrors(['estado' => 'No se puede cancelar una orden ya recibida.']);
        }

        $ordenesCompra->update(['estado' => 'cancelada']);
        $ordenesCompra->delete();

        return redirect()->route('ordenes-compra.index')
            ->with('message', 'Orden cancelada.');
    }
}
