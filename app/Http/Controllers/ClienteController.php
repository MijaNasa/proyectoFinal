<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Transaccion;
use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClienteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Cliente::query()->with(['user', 'tipoCliente']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('apellido', 'like', '%' . $search . '%')
                  ->orWhere('dni', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $clientes = $query->latest()->paginate(10)->withQueryString();

        return inertia('Clientes/Index', [
            'clientes' => $clientes,
            'tipos_clientes' => \App\Models\TipoCliente::get(['id', 'nombre', 'descuento_porcentaje']),
            'filters' => $request->only(['search'])
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

            $user->cliente()->create([
                'tipo_cliente_id' => $request->tipo_cliente_id,
                'estado_abono' => $request->estado_abono,
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
                'tipo_cliente_id' => $request->tipo_cliente_id,
                'estado_abono' => $request->estado_abono,
                'saldo_actual' => $request->saldo_actual,
            ]);
        });

        return redirect()->route('clientes.index')
            ->with('message', 'Cliente actualizado con éxito');
    }

    public function registrarPago(Request $request, Cliente $cliente)
    {
        $request->validate([
            'monto'       => 'required|numeric|min:0.01',
            'metodo_pago' => 'required|in:Efectivo,Transferencia,Tarjeta,Débito',
            'descripcion' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request, $cliente) {
            $cliente->increment('saldo_actual', $request->monto);

            Transaccion::create([
                'tipo'                 => 'ingreso',
                'monto'                => $request->monto,
                'metodo_pago'          => $request->metodo_pago,
                'fecha'                => now(),
                'sucursal_id'          => auth()->user()->empleado?->sucursal_id,
                'transaccionable_id'   => $cliente->id,
                'transaccionable_type' => Cliente::class,
                'descripcion'          => $request->descripcion ?: 'Pago de cuenta corriente',
                'user_id'              => auth()->id(),
            ]);
        });

        return redirect()->route('clientes.index')->with('message', 'Pago registrado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cliente $cliente)
    {
        \DB::transaction(function() use ($cliente) {
            $user = $cliente->user;
            $cliente->delete();
            // We usually don't delete the user if they could be an employee too, 
            // but here 1-1 structure suggests we might want to deactivate it.
            $user->update(['activo' => false]);
            $user->delete();
        });

        return redirect()->route('clientes.index')
            ->with('message', 'Cliente eliminado con éxito');
    }
}
