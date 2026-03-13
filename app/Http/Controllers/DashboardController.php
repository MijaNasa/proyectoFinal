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
    public function index()
    {
        $stats = [
            'ventas_hoy' => Venta::whereDate('fecha', now())->sum('total'),
            'cantidad_ventas' => Venta::whereDate('fecha', now())->count(),
            'stock_total' => (int) Stock::sum('cantidad_disponible'),
            'clientes_total' => Cliente::count(),
            'ultimas_ventas' => Venta::with(['cliente.user', 'sucursal'])
                ->latest()
                ->take(5)
                ->get(),
        ];

        return Inertia::render('Dashboard', [
            'stats' => $stats
        ]);
    }
}
