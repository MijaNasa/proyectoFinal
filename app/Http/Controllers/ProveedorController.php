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
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre_empresa', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }
        
        if ($request->has('estado')) {
            $query->where('activo', $request->estado === 'activos');
        } else {
            $query->where('activo', true); // Default to activos
        }

        $proveedores = $query->latest()->paginate(10)->withQueryString();

        return inertia('Proveedores/Index', [
            'proveedores' => $proveedores,
            'filters' => [
                'search' => $request->search ?? '',
                'estado' => $request->estado ?? 'activos'
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
        $proveedor->load(['series:id,nombre,activo,proveedor_id']);

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
                return [
                    'id' => 'orden_' . $orden->id,
                    'tipo' => 'deuda',
                    'fecha' => $orden->fecha . ' 00:00:00', // To match datetime format for sorting
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
            'metodo_pago' => 'required|in:Efectivo,Transferencia,Tarjeta,Débito',
            'descripcion' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request, $proveedor) {
            $proveedor->decrement('deuda_actual', $request->monto);

            Transaccion::create([
                'tipo'                 => 'egreso',
                'monto'                => $request->monto,
                'metodo_pago'          => $request->metodo_pago,
                'fecha'                => now(),
                'sucursal_id'          => auth()->user()->empleado?->sucursal_id,
                'transaccionable_id'   => $proveedor->id,
                'transaccionable_type' => Proveedor::class,
                'descripcion'          => $request->descripcion ?: 'Pago a proveedor',
                'user_id'              => auth()->id(),
            ]);
        });

        return redirect()->route('proveedores.index')->with('message', 'Pago registrado con éxito');
    }

    public function destroy(Proveedor $proveedor)
    {
        $tieneSeries = $proveedor->series()->exists();
        $tieneLibros = $proveedor->libroMasters()->exists();

        if ($tieneSeries || $tieneLibros) {
            return redirect()->back()
                ->with('error', 'No se puede eliminar el proveedor porque tiene obras o series asociadas.');
        }

        $proveedor->delete();

        return redirect()->route('proveedores.index')
            ->with('message', 'Proveedor eliminado con éxito');
    }
}
