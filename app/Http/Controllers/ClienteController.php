<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Transaccion;
use App\Models\Venta;
use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ClienteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Cliente::query()
            ->join('users', 'clientes.user_id', '=', 'users.id')
            ->select('clientes.*') // Select only cliente columns to avoid ID conflicts
            ->with(['user', 'tipoCliente']);

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('users.name', 'like', '%' . $search . '%')
                  ->orWhere('users.apellido', 'like', '%' . $search . '%')
                  ->orWhere('users.dni', 'like', '%' . $search . '%')
                  ->orWhere('users.email', 'like', '%' . $search . '%');
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'clientes.created_at');
        $sortDirection = $request->get('direction', 'desc');

        $sortableFields = [
            'cliente' => 'users.name',
            'dni' => 'users.dni',
            'contacto' => 'users.email',
            'saldo_actual' => 'clientes.saldo_actual',
        ];

        if (array_key_exists($sortField, $sortableFields)) {
            $query->orderBy($sortableFields[$sortField], $sortDirection);
        } else {
            $query->orderBy('clientes.created_at', 'desc');
        }

        $clientes = $query->paginate(10)->withQueryString();

        return inertia('Clientes/Index', [
            'clientes' => $clientes,
            'tipos_clientes' => \App\Models\TipoCliente::get(['id', 'nombre', 'descuento_porcentaje']),
            'filters' => $request->only(['search', 'sort', 'direction'])
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClienteRequest $request)
    {
        \DB::transaction(function() use ($request) {
            $user = \App\Models\User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => \Hash::make($request->dni ?: \Str::random(10)), // Default password is DNI or random
                'apellido' => $request->apellido,
                'dni' => $request->dni,
                'telefono' => $request->telefono,
                'activo' => true,
            ]);

            $tipoClienteId = $request->tipo_cliente_id ?? \App\Models\TipoCliente::first()?->id ?? 1;

            $user->cliente()->create([
                'tipo_cliente_id' => $tipoClienteId,
                'estado_abono' => $request->estado_abono ?? 'Activo',
                'saldo_actual' => $request->saldo_actual ?? 0,
            ]);
        });

        return redirect()->route('clientes.index')
            ->with('message', 'Cliente creado con éxito');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClienteRequest $request, Cliente $cliente)
    {
        \DB::transaction(function() use ($request, $cliente) {
            $cliente->user->update([
                'name' => $request->name,
                'email' => $request->email,
                'apellido' => $request->apellido,
                'dni' => $request->dni,
                'telefono' => $request->telefono,
            ]);

            $cliente->update([
                'tipo_cliente_id' => $request->tipo_cliente_id ?? $cliente->tipo_cliente_id,
                'estado_abono' => $request->estado_abono ?? $cliente->estado_abono,
                'saldo_actual' => $request->saldo_actual ?? $cliente->saldo_actual,
            ]);
        });

        return redirect()->route('clientes.index')
            ->with('message', 'Cliente actualizado con éxito');
    }

    public function show(Cliente $cliente)
    {
        $cliente->load(['user', 'tipoCliente', 'suscripciones.serie', 'suscripciones.sucursal']);

        $ventas = Venta::with(['detalles.libro.master:id,titulo', 'sucursal:id,nombre'])
            ->where('cliente_id', $cliente->id)
            ->where('estado', '!=', 'cancelado')
            ->latest('fecha')
            ->paginate(10);

        $canceladas = Venta::with(['detalles.libro.master:id,titulo', 'sucursal:id,nombre'])
            ->where('cliente_id', $cliente->id)
            ->where('estado', 'cancelado')
            ->latest('fecha')
            ->paginate(10, ['*'], 'canceladas_page');
            
        $acumulados = Venta::with(['detalles.libro.master:id,titulo'])
            ->where('cliente_id', $cliente->id)
            ->where('motivo_pendiente', 'Acumulación')
            ->where('estado', '!=', 'cancelado')
            ->latest('fecha')
            ->get();

        $pagos = $cliente->transacciones()
            ->where('tipo', 'ingreso')
            ->latest()
            ->get();

        $ultima_compra = Venta::where('cliente_id', $cliente->id)->latest('fecha')->value('fecha');

        $stats = [
            'total_comprado' => $ventas->sum('total'),
            'cantidad_ventas' => $ventas->total(),
            'total_pagado' => $pagos->sum('monto'),
            'ultima_compra' => $ultima_compra,
        ];

        return inertia('Clientes/Show', [
            'cliente' => $cliente,
            'ventas' => $ventas,
            'canceladas' => $canceladas,
            'acumulados' => $acumulados,
            'pagos' => $pagos,
            'stats' => $stats,
            'libro_masters' => \App\Models\LibroMaster::orderBy('titulo')->get(['id', 'titulo']),
            'sucursales' => \App\Models\Sucursal::get(['id', 'nombre'])
        ]);
    }

    public function registrarPago(Request $request, Cliente $cliente)
    {
        $request->validate([
            'monto'       => 'required|numeric|min:0.01',
            'metodo_pago' => 'required|in:Efectivo,Transferencia,Tarjeta,Débito,Cuenta Corriente',
            'fecha_real'  => 'required|date|before_or_equal:today',
            'descripcion' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request, $cliente) {
            $cliente->increment('saldo_actual', $request->monto);

            $fechaReal = \Carbon\Carbon::parse($request->fecha_real);
            $hoy = now();
            $descripcion = $request->descripcion ?: 'Pago de cuenta corriente';

            if ($fechaReal->lt($hoy->copy()->startOfDay())) {
                $descripcion = "Pago recibido el " . $fechaReal->format('d/m/Y') . " - Carga diferida. " . $descripcion;
            }

            Transaccion::create([
                'tipo'                 => 'ingreso',
                'monto'                => $request->monto,
                'metodo_pago'          => $request->metodo_pago,
                'fecha'                => now(),
                'sucursal_id'          => auth()->user()->empleado?->sucursal_id,
                'transaccionable_id'   => $cliente->id,
                'transaccionable_type' => Cliente::class,
                'descripcion'          => $descripcion,
                'user_id'              => auth()->id(),
            ]);
        });

        return redirect()->back()->with('success', 'Pago registrado con éxito');
    }

    public function eliminarPago(Cliente $cliente, Transaccion $transaccion)
    {
        DB::transaction(function () use ($cliente, $transaccion) {
            $cliente->decrement('saldo_actual', $transaccion->monto);
            $transaccion->delete();
        });

        return redirect()->back()->with('success', 'Pago anulado exitosamente');
    }

    public function generarResumenPdf(Cliente $cliente)
    {
        $cliente->load('user');

        $ventas = Venta::with('sucursal')
            ->where('cliente_id', $cliente->id)
            ->where('estado', '!=', 'cancelado')
            ->get()
            ->map(function ($venta) {
                return (object)[
                    'fecha'       => $venta->fecha,
                    'tipo_mov'    => 'Compra',
                    'descripcion' => 'Venta #TK-' . str_pad($venta->id, 6, '0', STR_PAD_LEFT),
                    'sucursal'    => $venta->sucursal->nombre ?? 'Online',
                    'monto'       => -$venta->total,
                ];
            });

        $pagos = $cliente->transacciones()
            ->with('sucursal')
            ->where('tipo', 'ingreso')
            ->get()
            ->map(function ($pago) {
                return (object)[
                    'fecha'       => $pago->fecha,
                    'tipo_mov'    => 'Pago',
                    'descripcion' => $pago->descripcion ?: 'Pago en ' . $pago->metodo_pago,
                    'sucursal'    => $pago->sucursal->nombre ?? '-',
                    'monto'       => $pago->monto,
                ];
            });

        $historial = $ventas->concat($pagos)->sortBy('fecha')->values();

        $pdf = Pdf::loadView('pdf.resumen_cliente', compact('cliente', 'historial'));

        return $pdf->download('Resumen_Cuenta_' . $cliente->user->apellido . '.pdf');
    }

    public function consolidarPedidos(Request $request, Cliente $cliente)
    {
        $request->validate([
            'direccion_envio' => 'required|string|max:255',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
        ]);

        \DB::transaction(function () use ($cliente, $request) {
            $ventas = Venta::where('cliente_id', $cliente->id)
                ->where('motivo_pendiente', 'Acumulación')
                ->lockForUpdate()
                ->get();

            foreach ($ventas as $venta) {
                $venta->update([
                    'estado' => 'en_preparacion',
                    'direccion_envio' => $request->direccion_envio,
                    'latitud' => $request->latitud,
                    'longitud' => $request->longitud,
                    'motivo_pendiente' => null, // Ya no está en acumulación
                ]);
            }
        });

        return back()->with('message', 'Pedidos consolidados y listos para reparto.');
    }

    public function destroyCanceladas(Cliente $cliente)
    {
        \DB::transaction(function() use ($cliente) {
            $canceladas = \App\Models\Venta::where('cliente_id', $cliente->id)
                ->where('estado', 'cancelado')
                ->get();
                
            foreach ($canceladas as $venta) {
                $venta->transacciones()->delete();
                $venta->detalles()->delete();
                $venta->delete();
            }
        });

        return back()->with('message', 'Todas las ventas canceladas han sido eliminadas permanentemente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cliente $cliente)
    {
        $saldo = (float) $cliente->saldo_actual;
        if (abs($saldo) >= 0.01) {
            $msg = $saldo < 0 
                ? 'No se puede eliminar el cliente porque posee una deuda pendiente ($' . number_format(abs($saldo), 2, ',', '.') . '). Debe saldarla antes.'
                : 'No se puede eliminar el cliente porque posee un saldo a favor ($' . number_format($saldo, 2, ',', '.') . '). Debe consumirse o devolverse antes.';
            return redirect()->route('clientes.index')->with('error_modal', $msg);
        }

        \DB::transaction(function() use ($cliente) {
            $user = $cliente->user;
            $cliente->delete();
            if ($user) {
                $user->update(['activo' => false]);
                $user->delete();
            }
        });

        return redirect()->route('clientes.index')
            ->with('message', 'Cliente eliminado con éxito. Las compras históricas conservan sus datos intactos.');
    }
}
