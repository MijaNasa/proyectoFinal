<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GastoController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $request->validate([
            'desde'      => 'nullable|date',
            'hasta'      => 'nullable|date',
            'sucursal_id'=> 'nullable|integer|exists:sucursales,id',
            'categoria'  => 'nullable|string',
        ]);

        $desde      = $request->get('desde', now()->startOfMonth()->toDateString());
        $hasta      = $request->get('hasta', now()->toDateString());
        $sucursalId = $request->get('sucursal_id');
        $categoria  = $request->get('categoria');

        $query = Gasto::with(['sucursal:id,nombre', 'user:id,name,apellido'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))
            ->when($categoria,  fn($q) => $q->where('categoria', $categoria))
            ->latest('fecha');

        $gastos = $query->paginate(25)->withQueryString();

        $stats = Gasto::whereBetween('fecha', [$desde, $hasta])
            ->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))
            ->selectRaw('COUNT(*) as cantidad, SUM(monto) as total')
            ->groupBy(DB::raw('1=1'))
            ->first();

        $porCategoria = Gasto::whereBetween('fecha', [$desde, $hasta])
            ->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))
            ->select('categoria', DB::raw('SUM(monto) as total'), DB::raw('COUNT(*) as cantidad'))
            ->groupBy('categoria')
            ->orderByDesc('total')
            ->get();

        return inertia('Gastos/Index', [
            'gastos'       => $gastos,
            'stats'        => [
                'total'    => (float) ($stats->total ?? 0),
                'cantidad' => (int)   ($stats->cantidad ?? 0),
            ],
            'porCategoria' => $porCategoria,
            'sucursales'   => Sucursal::orderBy('nombre')->get(['id', 'nombre']),
            'filters'      => compact('desde', 'hasta', 'sucursalId', 'categoria'),
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'concepto'     => 'required|string|max:255',
            'categoria'    => 'required|in:alquiler,servicios,sueldos,insumos,impuestos,mantenimiento,otros',
            'monto'        => 'required|numeric|min:0.01',
            'fecha'        => 'required|date',
            'metodo_pago'  => 'required|string|max:50',
            'comprobante'  => 'nullable|string|max:100',
            'observaciones'=> 'nullable|string|max:500',
            'sucursal_id'  => 'required|exists:sucursales,id',
        ]);

        DB::transaction(function () use ($data) {
            $gasto = Gasto::create(array_merge($data, ['user_id' => auth()->id()]));

            // Registrar como transacción egreso para cierre de caja
            $gasto->transaccion()->create([
                'tipo'        => 'egreso',
                'monto'       => $gasto->monto,
                'metodo_pago' => $gasto->metodo_pago,
                'fecha'       => $gasto->fecha,
                'sucursal_id' => $gasto->sucursal_id,
                'descripcion' => "[Gasto] {$gasto->concepto}",
                'user_id'     => auth()->id(),
            ]);
        });

        return back()->with('message', 'Gasto registrado correctamente');
    }

    public function update(Request $request, Gasto $gasto): \Illuminate\Http\RedirectResponse
    {
        $user = auth()->user();
        if (!$user->esAdmin() && $gasto->sucursal_id !== $user->empleado?->sucursal_id) {
            abort(403);
        }

        $data = $request->validate([
            'concepto'     => 'required|string|max:255',
            'categoria'    => 'required|in:alquiler,servicios,sueldos,insumos,impuestos,mantenimiento,otros',
            'monto'        => 'required|numeric|min:0.01',
            'fecha'        => 'required|date',
            'metodo_pago'  => 'required|string|max:50',
            'comprobante'  => 'nullable|string|max:100',
            'observaciones'=> 'nullable|string|max:500',
            'sucursal_id'  => 'required|exists:sucursales,id',
        ]);

        DB::transaction(function () use ($data, $gasto) {
            $gasto->update($data);

            // Sincronizar transacción asociada
            $gasto->transaccion()->updateOrCreate(
                [],
                [
                    'tipo'        => 'egreso',
                    'monto'       => $gasto->monto,
                    'metodo_pago' => $gasto->metodo_pago,
                    'fecha'       => $gasto->fecha,
                    'sucursal_id' => $gasto->sucursal_id,
                    'descripcion' => "[Gasto] {$gasto->concepto}",
                    'user_id'     => auth()->id(),
                ]
            );
        });

        return back()->with('message', 'Gasto actualizado correctamente');
    }

    public function destroy(Gasto $gasto): \Illuminate\Http\RedirectResponse
    {
        $user = auth()->user();
        if (!$user->esAdmin() && $gasto->sucursal_id !== $user->empleado?->sucursal_id) {
            abort(403);
        }

        DB::transaction(function () use ($gasto) {
            $gasto->transaccion()?->delete();
            $gasto->delete();
        });

        return back()->with('message', 'Gasto eliminado correctamente');
    }
}
