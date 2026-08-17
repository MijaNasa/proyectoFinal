<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use App\Models\Transaccion;
use App\Http\Requests\StoreProveedorRequest;
use App\Http\Requests\UpdateProveedorRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        $query = Proveedor::query();

        if ($request->has('search')) {
            $like = '%' . mb_strtolower($request->search) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(nombre_empresa) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
            });
        }
        
        if ($request->has('estado')) {
            $query->where('activo', $request->estado === 'activos');
        } else {
            $query->where('activo', true); // Default to activos
        }

        $sort = $request->get('sort', 'latest');
        $direction = strtolower($request->get('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($sort === 'nombre') {
            $query->orderBy('nombre_empresa', $direction);
        } elseif ($sort === 'deuda') {
            $query->orderBy('deuda_actual', $direction);
        } else {
            $query->latest();
        }

        $proveedores = $query->paginate(10)->withQueryString();

        return inertia('Proveedores/Index', [
            'proveedores' => $proveedores,
            'filters' => [
                'search' => $request->search ?? '',
                'estado' => $request->estado ?? 'activos',
                'sort' => $sort,
                'direction' => $direction,
            ]
        ]);
    }

    public function store(StoreProveedorRequest $request)
    {
        Proveedor::create($request->validated());

        return redirect()->route('proveedores.index')
            ->with('message', 'Proveedor creado con éxito');
    }

    public function update(UpdateProveedorRequest $request, Proveedor $proveedor)
    {
        $proveedor->update($request->validated());

        return redirect()->route('proveedores.index')
            ->with('message', 'Proveedor actualizado con éxito');
    }

    public function show(Proveedor $proveedor)
    {
        $pagos = $proveedor->transacciones()
            ->latest('fecha')
            ->get(['id', 'monto', 'metodo_pago', 'descripcion', 'fecha'])
            ->map(function ($pago) {
                return [
                    'id' => 'pago_' . $pago->id,
                    'tipo' => 'pago',
                    'fecha' => $pago->fecha,
                    'descripcion' => $pago->descripcion ?: 'Pago registrado',
                    'metodo_pago' => $pago->metodo_pago,
                    'monto' => $pago->monto,
                ];
            });
            
        $ordenes = \App\Models\OrdenCompra::where('proveedor_id', $proveedor->id)
            ->where('estado', 'recibida')
            ->latest('fecha')
            ->get(['id', 'numero_orden', 'fecha', 'total'])
            ->map(function ($orden) {
                $fechaVal = $orden->fecha;
                if ($fechaVal instanceof \Illuminate\Support\Carbon) {
                    $fechaVal = $fechaVal->toIso8601String();
                }
                return [
                    'id' => 'orden_' . $orden->id,
                    'real_id' => $orden->id,
                    'numero_orden' => $orden->numero_orden,
                    'tipo' => 'deuda',
                    'fecha' => $fechaVal,
                    'descripcion' => 'Orden de Compra ' . $orden->numero_orden,
                    'metodo_pago' => 'Cuenta Corriente',
                    'monto' => $orden->total,
                ];
            });
            
        $historial = $pagos->concat($ordenes)->sortByDesc('fecha')->values();

        $stats = [
            'total_deuda_historica' => (float) $ordenes->sum('monto'),
            'total_pagado'    => (float) $pagos->sum('monto'),
            'deuda_actual'    => (float) $proveedor->deuda_actual,
            'cantidad_pagos'  => (int)   $pagos->count(),
        ];

        return inertia('Proveedores/Show', [
            'proveedor' => $proveedor,
            'historial' => $historial,
            'stats'     => $stats,
        ]);
    }

    public function registrarPago(Request $request, Proveedor $proveedor)
    {
        $request->validate([
            'monto'       => 'required|numeric|min:0.01',
            'metodo_pago' => 'required|in:Efectivo,Transferencia,Tarjeta,Débito,Mercado Pago',
            'fecha'       => 'required|date',
            'comprobante' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request, $proveedor) {
            $proveedor->decrement('deuda_actual', $request->monto);

            $fechaHora = $request->fecha . ' ' . date('H:i:s');

            Transaccion::create([
                'tipo'                 => 'egreso',
                'monto'                => $request->monto,
                'metodo_pago'          => $request->metodo_pago,
                'fecha'                => $fechaHora,
                'sucursal_id'          => auth()->user()->empleado?->sucursal_id,
                'transaccionable_id'   => $proveedor->id,
                'transaccionable_type' => Proveedor::class,
                'descripcion'          => $request->descripcion ?: 'Pago a proveedor',
                'comprobante'          => $request->comprobante,
                'user_id'              => auth()->id(),
            ]);
        });

        return redirect()->back()->with('message', 'Pago registrado con éxito');
    }

    public function destroy(Proveedor $proveedor)
    {
        $tieneLibros = \App\Models\LibroMaster::where('proveedor_id', $proveedor->id)->exists();
        if ($tieneLibros) {
            return redirect()->route('proveedores.index')
                ->with('error_modal', 'No se puede eliminar el proveedor porque posee series (obras) asociadas. Debe desvincularlas o eliminarlas antes.');
        }

        if ($proveedor->deuda_actual > 0) {
            $montoFormateado = '$' . number_format($proveedor->deuda_actual, 2, ',', '.');
            return redirect()->route('proveedores.index')
                ->with('error_modal', "No se puede eliminar el proveedor porque posee una deuda pendiente ({$montoFormateado}). Debe saldarse antes.");
        }

        $proveedor->delete();

        return redirect()->route('proveedores.index')
            ->with('message', 'Proveedor eliminado con éxito');
    }
}
