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
            return redirect()->route('mi-cuenta.index');
        }

        $hoy = now();
        $sucursalId = $user->sucursalRestringidaId();

        $ventasHoy = Venta::where('estado', '!=', 'cancelado')
            ->whereBetween('fecha', [$hoy->copy()->startOfDay(), $hoy->copy()->endOfDay()])
            ->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total),0) as recaudacion')
            ->first();

        $ventasMes = Venta::where('estado', '!=', 'cancelado')
            ->whereBetween('fecha', [$hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()])
            ->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))
            ->selectRaw('COALESCE(SUM(total),0) as recaudacion')
            ->value('recaudacion');

        $stats = [
            'ventas_hoy'              => (float) $ventasHoy->recaudacion,
            'cantidad_ventas'         => (int)   $ventasHoy->cantidad,
            'ventas_mes'              => (float) $ventasMes,
            'clientes_total'          => (int)   Cliente::count(),
            'pedidos_online_pendientes' => (int) Venta::where('tipo', 'online')
                ->whereIn('estado', ['pendiente_pago', 'en_preparacion'])
                ->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))
                ->count(),
            'ultimas_ventas' => Venta::with(['cliente.user:id,name,apellido', 'user:id,name,apellido', 'sucursal:id,nombre'])
                ->where('estado', '!=', 'cancelado')
                ->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))
                ->latest()
                ->take(6)
                ->get(['id', 'fecha', 'total', 'estado', 'tipo', 'cliente_id', 'user_id', 'sucursal_id']),
            'ultimos_movimientos' => MovimientoStock::with([
                    'origen:id,nombre',
                    'destino:id,nombre',
                    'detalles.libro.master:id,titulo'
                ])
                ->when($sucursalId, fn($q) => $q->where(function ($sq) use ($sucursalId) {
                    $sq->where('sucursal_origen_id', $sucursalId)
                       ->orWhere('sucursal_destino_id', $sucursalId);
                }))
                ->latest()
                ->take(6)
                ->get(['id', 'tipo', 'sucursal_origen_id', 'sucursal_destino_id', 'created_at']),
        ];

        return Inertia::render('Dashboard', [
            'stats' => $stats
        ]);
    }
}
