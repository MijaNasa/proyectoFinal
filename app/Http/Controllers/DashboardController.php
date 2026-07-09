<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\Cliente;
use App\Models\MovimientoStock;
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

        $ventasMes = Venta::whereBetween('fecha', [$hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()])
            ->selectRaw('COALESCE(SUM(total),0) as recaudacion')
            ->value('recaudacion');

        $stats = [
            'ventas_hoy'              => (float) $ventasHoy->recaudacion,
            'cantidad_ventas'         => (int)   $ventasHoy->cantidad,
            'ventas_mes'              => (float) $ventasMes,
            'clientes_total'          => (int)   Cliente::count(),
            'pedidos_online_pendientes' => (int) Venta::where('tipo', 'online')
                ->whereIn('estado', ['pendiente_pago', 'en_preparacion'])
                ->count(),
            'ultimas_ventas' => Venta::with(['cliente.user:id,name,apellido', 'user:id,name,apellido', 'sucursal:id,nombre'])
                ->latest()
                ->take(6)
                ->get(['id', 'fecha', 'total', 'estado', 'tipo', 'cliente_id', 'user_id', 'sucursal_id']),
            'ultimos_movimientos' => MovimientoStock::with([
                    'stock.libro.master:id,titulo',
                    'stock.sucursal:id,nombre',
                    'tipoMovimiento:id,nombre,codigo',
                ])
                ->latest('fecha_movimiento')
                ->take(6)
                ->get(['id', 'stock_id', 'tipo_movimiento_id', 'cantidad', 'cantidad_nueva', 'fecha_movimiento']),
        ];

        return Inertia::render('Dashboard', [
            'stats' => $stats
        ]);
    }
}
