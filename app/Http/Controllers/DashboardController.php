<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\Libro;
use App\Models\Stock;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user->esAdmin() && !$user->empleado) {
            return redirect()->route('catalogo.index');
        }

        $hoy = now();
        $ventasHoy = Venta::whereBetween('fecha', [$hoy->copy()->startOfDay(), $hoy->copy()->endOfDay()])
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total),0) as recaudacion')
            ->first();

        $stats = [
            'ventas_hoy'     => (float) $ventasHoy->recaudacion,
            'cantidad_ventas' => (int) $ventasHoy->cantidad,
            'stock_total'    => (int) Stock::sum('cantidad_disponible'),
            'clientes_total' => Cliente::count(),
            'ultimas_ventas' => Venta::with(['cliente.user:id,name,apellido', 'sucursal:id,nombre'])
                ->latest()
                ->take(5)
                ->get(['id', 'fecha', 'total', 'estado', 'cliente_id', 'sucursal_id']),
        ];

        return Inertia::render('Dashboard', [
            'stats' => $stats
        ]);
    }
}
