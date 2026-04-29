<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\Empleado;
use App\Http\Requests\StoreEmpleadoRequest;
use App\Http\Requests\UpdateEmpleadoRequest;
use Illuminate\Http\Request;

class EmpleadoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Empleado::query()->with([
            'user',
            'sucursal',
            'cargos' => fn($q) => $q->wherePivotNull('fecha_hasta'),
        ]);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('legajo', 'like', '%' . $search . '%')
                  ->orWhereHas('user', function($sq) use ($search) {
                      $sq->where('name', 'like', '%' . $search . '%')
                        ->orWhere('apellido', 'like', '%' . $search . '%')
                        ->orWhere('dni', 'like', '%' . $search . '%');
                  });
            });
        }

        $empleados = $query->latest()->paginate(10)->withQueryString();

        return inertia('Empleados/Index', [
            'empleados' => $empleados,
            'sucursales' => \App\Models\Sucursal::where('activo', true)->get(['id', 'nombre']),
            'cargos'     => Cargo::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'filters'    => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmpleadoRequest $request)
    {
        \DB::transaction(function() use ($request) {
            $user = \App\Models\User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => \Hash::make($request->dni ?: \Str::random(10)),
                'apellido' => $request->apellido,
                'dni' => $request->dni,
                'telefono' => $request->telefono,
                'active' => true,
            ]);

            $user->empleado()->create([
                'legajo' => $request->legajo,
                'sucursal_id' => $request->sucursal_id,
                'fecha_ingreso' => $request->fecha_ingreso,
            ]);
        });

        return redirect()->route('empleados.index')
            ->with('message', 'Empleado registrado con éxito');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmpleadoRequest $request, Empleado $empleado)
    {
        \DB::transaction(function() use ($request, $empleado) {
            $empleado->user->update([
                'name' => $request->name,
                'email' => $request->email,
                'apellido' => $request->apellido,
                'dni' => $request->dni,
                'telefono' => $request->telefono,
            ]);

            $empleado->update($request->only(['legajo', 'sucursal_id', 'fecha_ingreso', 'fecha_egreso']));
        });

        return redirect()->route('empleados.index')
            ->with('message', 'Empleado actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Empleado $empleado)
    {
        \DB::transaction(function() use ($empleado) {
            $user = $empleado->user;
            $empleado->delete();
            $user->update(['active' => false]);
            $user->delete();
        });

        return redirect()->route('empleados.index')
            ->with('message', 'Empleado eliminado con éxito');
    }

    public function asignarCargo(Request $request, Empleado $empleado)
    {
        $request->validate(['cargo_id' => 'required|exists:cargos,id']);

        $user = $request->user();

        // Gerente solo puede gestionar empleados de su sucursal
        if (!$user->esAdmin() && $user->empleado?->sucursal_id !== $empleado->sucursal_id) {
            abort(403, 'Solo podés asignar cargos a empleados de tu sucursal.');
        }

        if ($empleado->cargos()->where('cargo_id', $request->cargo_id)->wherePivotNull('fecha_hasta')->exists()) {
            return back()->with('error', 'El empleado ya tiene ese cargo activo.');
        }

        $empleado->cargos()->attach($request->cargo_id, ['fecha_desde' => now()->toDateString()]);

        return back()->with('message', 'Cargo asignado con éxito');
    }

    public function desasignarCargo(Request $request, Empleado $empleado, Cargo $cargo)
    {
        $user = $request->user();

        if (!$user->esAdmin() && $user->empleado?->sucursal_id !== $empleado->sucursal_id) {
            abort(403, 'Solo podés gestionar empleados de tu sucursal.');
        }

        $empleado->cargos()
            ->wherePivot('cargo_id', $cargo->id)
            ->wherePivotNull('fecha_hasta')
            ->update(['fecha_hasta' => now()->toDateString()]);

        return back()->with('message', 'Cargo removido');
    }
}
