<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Venta;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\Libro;
use App\Models\Cliente;

class ReporteController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $request->validate([
            'desde'      => 'nullable|date',
            'hasta'      => 'nullable|date',
            'sucursal_id'=> 'nullable|integer|exists:sucursales,id',
            'tab'        => 'nullable|string|in:ventas,stock,balance',
        ]);

        $tab       = $request->get('tab', 'ventas');
        $desde     = $request->get('desde', now()->startOfMonth()->toDateString());
        $hasta     = $request->get('hasta', now()->toDateString());
        $sucursalId = $request->user()->sucursalRestringidaId() ?: $request->get('sucursal_id');

        return Inertia::render('Reportes/Index', [
            'tab'       => $tab,
            'filters'   => compact('desde', 'hasta', 'sucursalId'),
            'sucursales' => Sucursal::orderBy('nombre')->when($request->user()->sucursalRestringidaId(), fn($q, $sid) => $q->where('id', $sid))->get(['id', 'nombre']),
            'reporteVentas' => $tab === 'ventas'   ? $this->reporteVentas($desde, $hasta, $sucursalId) : null,
            'reporteStock'  => $tab === 'stock'    ? $this->reporteStock($sucursalId) : null,
            'reporteBalance'=> $tab === 'balance'  ? $this->reporteBalance($desde, $hasta, $sucursalId) : null,
        ]);
    }

    private function reporteVentas(string $desde, string $hasta, ?string $sucursalId): array
    {
        $base = Venta::where('fecha', '>=', $desde)
            ->where('fecha', '<=', \Carbon\Carbon::parse($hasta)->endOfDay())
            ->whereNotIn('estado', ['cancelado', 'pendiente_pago']);
        if ($sucursalId) $base->where('sucursal_id', $sucursalId);

        // Ventas por día
        $porDia = (clone $base)
            ->select(DB::raw('DATE(fecha) as dia'), DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(total) as total'))
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->map(fn($r) => ['dia' => $r->dia, 'cantidad' => (int)$r->cantidad, 'total' => (float)$r->total]);

        // Top productos
        $topProductos = DB::table('venta_detalles')
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->join('libros', 'libros.id', '=', 'venta_detalles.libro_id')
            ->join('libro_masters', 'libro_masters.id', '=', 'libros.master_id')
            ->where('ventas.fecha', '>=', $desde)
            ->where('ventas.fecha', '<=', \Carbon\Carbon::parse($hasta)->endOfDay())
            ->when($sucursalId, fn($q) => $q->where('ventas.sucursal_id', $sucursalId))
            ->whereNull('ventas.deleted_at')
            ->whereNotIn('ventas.estado', ['cancelado', 'pendiente_pago'])
            ->select(
                'libro_masters.titulo',
                DB::raw('SUM(venta_detalles.cantidad) as unidades'),
                DB::raw('SUM(venta_detalles.subtotal) as total')
            )
            ->groupBy('libro_masters.id', 'libro_masters.titulo')
            ->orderByDesc('unidades')
            ->limit(10)
            ->get()
            ->map(fn($r) => ['titulo' => $r->titulo, 'unidades' => (int)$r->unidades, 'total' => (float)$r->total]);

        // Top clientes
        $topClientes = (clone $base)
            ->join('clientes', 'clientes.id', '=', 'ventas.cliente_id')
            ->join('users', 'users.id', '=', 'clientes.user_id')
            ->select(
                DB::raw("users.name || ' ' || COALESCE(users.apellido, '') as nombre"),
                DB::raw('COUNT(ventas.id) as compras'),
                DB::raw('SUM(ventas.total) as total')
            )
            ->groupBy('clientes.id', 'users.name', 'users.apellido')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn($r) => ['nombre' => trim($r->nombre), 'compras' => (int)$r->compras, 'total' => (float)$r->total]);

        // Resumen por tipo
        $porTipo = (clone $base)
            ->select('tipo', DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(total) as total'))
            ->groupBy('tipo')
            ->get()
            ->map(fn($r) => ['tipo' => $r->tipo, 'cantidad' => (int)$r->cantidad, 'total' => (float)$r->total]);

        $totales = (clone $base)->selectRaw('COUNT(*) as cantidad, SUM(total) as total')->first();

        return compact('porDia', 'topProductos', 'topClientes', 'porTipo', 'totales');
    }

    private function reporteStock(?string $sucursalId): array
    {
        // Stock por sucursal
        $porSucursal = DB::table('stocks')
            ->join('sucursales', 'sucursales.id', '=', 'stocks.sucursal_id')
            ->when($sucursalId, fn($q) => $q->where('stocks.sucursal_id', $sucursalId))
            ->select(
                'sucursales.nombre',
                DB::raw('SUM(stocks.cantidad_disponible) as disponible'),
                DB::raw('SUM(stocks.cantidad_reservada) as reservada'),
                DB::raw('COUNT(*) as titulos')
            )
            ->groupBy('sucursales.id', 'sucursales.nombre')
            ->orderBy('sucursales.nombre')
            ->get()
            ->map(fn($r) => [
                'nombre'    => $r->nombre,
                'disponible'=> (int)$r->disponible,
                'reservada' => (int)$r->reservada,
                'titulos'   => (int)$r->titulos,
            ]);

        // Sin stock (disponible = 0)
        $sinStock = DB::table('stocks')
            ->join('libros', 'libros.id', '=', 'stocks.libro_id')
            ->join('libro_masters', 'libro_masters.id', '=', 'libros.master_id')
            ->join('sucursales', 'sucursales.id', '=', 'stocks.sucursal_id')
            ->when($sucursalId, fn($q) => $q->where('stocks.sucursal_id', $sucursalId))
            ->where('stocks.cantidad_disponible', '<=', 0)
            ->select('libro_masters.titulo', 'sucursales.nombre as sucursal', 'stocks.cantidad_disponible')
            ->orderBy('libro_masters.titulo')
            ->get()
            ->map(fn($r) => ['titulo' => $r->titulo, 'sucursal' => $r->sucursal, 'disponible' => (int)$r->cantidad_disponible]);

        // Stock bajo (1-5)
        $stockBajo = DB::table('stocks')
            ->join('libros', 'libros.id', '=', 'stocks.libro_id')
            ->join('libro_masters', 'libro_masters.id', '=', 'libros.master_id')
            ->join('sucursales', 'sucursales.id', '=', 'stocks.sucursal_id')
            ->when($sucursalId, fn($q) => $q->where('stocks.sucursal_id', $sucursalId))
            ->whereBetween('stocks.cantidad_disponible', [1, 5])
            ->select('libro_masters.titulo', 'sucursales.nombre as sucursal', 'stocks.cantidad_disponible')
            ->orderBy('stocks.cantidad_disponible')
            ->limit(20)
            ->get()
            ->map(fn($r) => ['titulo' => $r->titulo, 'sucursal' => $r->sucursal, 'disponible' => (int)$r->cantidad_disponible]);

        $totales = [
            'total_unidades' => (int) DB::table('stocks')
                ->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))
                ->sum('cantidad_disponible'),
            'titulos_unicos' => (int) DB::table('stocks')
                ->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))
                ->distinct()->count('libro_id'),
            'sin_stock' => count($sinStock),
            'stock_bajo' => count($stockBajo),
        ];

        return compact('porSucursal', 'sinStock', 'stockBajo', 'totales');
    }

    private function reporteBalance(string $desde, string $hasta, ?string $sucursalId): array
    {
        $base = Venta::where('fecha', '>=', $desde)
            ->where('fecha', '<=', \Carbon\Carbon::parse($hasta)->endOfDay())
            ->whereNotIn('estado', ['cancelado', 'pendiente_pago']);
        if ($sucursalId) $base->where('sucursal_id', $sucursalId);

        $baseWithDetalles = (clone $base)
            ->join('venta_detalles', 'venta_detalles.venta_id', '=', 'ventas.id');

        // Ingresos por mes
        $porMes = (clone $baseWithDetalles)
            ->select(
                DB::raw("strftime('%Y-%m', ventas.fecha) as mes"),
                DB::raw('SUM(venta_detalles.subtotal) as ingresos'),
                DB::raw('SUM(venta_detalles.cantidad * COALESCE(venta_detalles.costo_unitario, 0)) as cogs'),
                DB::raw('COUNT(DISTINCT ventas.id) as ventas')
            )
            ->groupBy(DB::raw("strftime('%Y-%m', ventas.fecha)"))
            ->orderBy(DB::raw("strftime('%Y-%m', ventas.fecha)"))
            ->get()
            ->map(fn($r) => [
                'mes' => $r->mes, 
                'ingresos' => (float)$r->ingresos, 
                'cogs' => (float)$r->cogs,
                'rentabilidad' => (float)($r->ingresos - $r->cogs),
                'ventas' => (int)$r->ventas
            ]);

        // Por sucursal
        $porSucursal = (clone $baseWithDetalles)
            ->join('sucursales', 'sucursales.id', '=', 'ventas.sucursal_id')
            ->select(
                'sucursales.nombre', 
                DB::raw('SUM(venta_detalles.subtotal) as ingresos'), 
                DB::raw('SUM(venta_detalles.cantidad * COALESCE(venta_detalles.costo_unitario, 0)) as cogs'),
                DB::raw('COUNT(DISTINCT ventas.id) as ventas')
            )
            ->groupBy('sucursales.id', 'sucursales.nombre')
            ->orderByDesc('ingresos')
            ->get()
            ->map(fn($r) => [
                'nombre' => $r->nombre, 
                'ingresos' => (float)$r->ingresos, 
                'cogs' => (float)$r->cogs,
                'rentabilidad' => (float)($r->ingresos - $r->cogs),
                'ventas' => (int)$r->ventas
            ]);

        // Por tipo de venta
        $porTipo = (clone $baseWithDetalles)
            ->select(
                'ventas.tipo', 
                DB::raw('SUM(venta_detalles.subtotal) as ingresos'), 
                DB::raw('SUM(venta_detalles.cantidad * COALESCE(venta_detalles.costo_unitario, 0)) as cogs'),
                DB::raw('COUNT(DISTINCT ventas.id) as ventas')
            )
            ->groupBy('ventas.tipo')
            ->get()
            ->map(fn($r) => [
                'tipo' => $r->tipo, 
                'ingresos' => (float)$r->ingresos, 
                'cogs' => (float)$r->cogs,
                'rentabilidad' => (float)($r->ingresos - $r->cogs),
                'ventas' => (int)$r->ventas
            ]);

        $totales = (clone $baseWithDetalles)->selectRaw('
            SUM(venta_detalles.subtotal) as ingresos, 
            SUM(venta_detalles.cantidad * COALESCE(venta_detalles.costo_unitario, 0)) as cogs,
            COUNT(DISTINCT ventas.id) as ventas
        ')->first();

        return [
            'porMes'       => $porMes,
            'porSucursal'  => $porSucursal,
            'porTipo'      => $porTipo,
            'totalIngresos'=> (float)($totales->ingresos ?? 0),
            'totalCogs'    => (float)($totales->cogs ?? 0),
            'totalRentabilidad' => (float)(($totales->ingresos ?? 0) - ($totales->cogs ?? 0)),
            'totalVentas'  => (int)($totales->ventas ?? 0),
            'ticketPromedio'=> $totales && $totales->ventas > 0 ? round($totales->ingresos / $totales->ventas, 2) : 0,
        ];
    }
}
