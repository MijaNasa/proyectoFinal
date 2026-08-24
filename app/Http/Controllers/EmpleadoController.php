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
            'cargos' => fn($q) => $q->whereNull('empleados_cargos.fecha_hasta'),
        ]);

        if ($request->has('search')) {
            $like = '%' . mb_strtolower($request->search) . '%';
            $query->where(function($q) use ($like) {
                $q->whereRaw('LOWER(legajo) LIKE ?', [$like])
                  ->orWhereHas('user', function($sq) use ($like) {
                      $sq->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(apellido) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(dni) LIKE ?', [$like]);
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
                'activo' => true,
            ]);

            $legajo = $request->filled('legajo') 
                ? $request->legajo 
                : ('LEG-' . str_pad($user->id, 4, '0', STR_PAD_LEFT));

            $empleado = $user->empleado()->create([
                'legajo' => $legajo,
                'sucursal_id' => $request->sucursal_id,
                'fecha_ingreso' => $request->fecha_ingreso,
            ]);

            if ($request->filled('cargo_id')) {
                $empleado->cargos()->attach($request->cargo_id, ['fecha_desde' => now()->toDateString()]);
            }
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

            if ($request->has('cargo_id')) {
                $currentCargoIds = $empleado->cargos()->pluck('cargos.id')->toArray();
                $newCargoId = $request->cargo_id;

                if (!in_array($newCargoId, $currentCargoIds) || (!$newCargoId && $currentCargoIds)) {
                    $empleado->cargos()->updateExistingPivot($currentCargoIds, ['fecha_hasta' => now()->toDateString()]);
                    
                    if ($newCargoId) {
                        $empleado->cargos()->attach($newCargoId, ['fecha_desde' => now()->toDateString()]);
                    }
                }
            }
        });

        return redirect()->route('empleados.index')
            ->with('message', 'Empleado actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Empleado $empleado)
    {
        $tieneRutasActivas = \App\Models\RutaReparto::where('repartidor_id', $empleado->id)
            ->where('estado', '!=', 'finalizada')
            ->exists();

        if ($tieneRutasActivas) {
            return redirect()->route('empleados.index')
                ->with('error', 'No se puede eliminar el empleado porque tiene rutas de reparto asignadas.');
        }

        \DB::transaction(function() use ($empleado) {
            $user = $empleado->user;
            $empleado->delete();
            $user->update(['activo' => false]);
            $user->delete();
        });

        return redirect()->route('empleados.index')
            ->with('message', 'Empleado eliminado con éxito');
    }

    public function resetearPassword(Request $request, Empleado $empleado)
    {
        $user = $request->user();

        if (!$user->esAdmin() && $user->empleado?->sucursal_id !== $empleado->sucursal_id) {
            abort(403, 'Solo podés gestionar empleados de tu sucursal.');
        }

        $nuevaPassword = $empleado->user->dni ?: \Str::random(8);
        $empleado->user->update(['password' => \Hash::make($nuevaPassword)]);

        return back()->with('nuevaPassword', $nuevaPassword);
    }

    public function asignarCargo(Request $request, Empleado $empleado)
    {
        $request->validate(['cargo_id' => 'required|exists:cargos,id']);

        $user = $request->user();

        if (!$user->esAdmin() && $user->empleado?->sucursal_id !== $empleado->sucursal_id) {
            abort(403, 'Solo podés asignar cargos a empleados de tu sucursal.');
        }

        $cargo = Cargo::findOrFail($request->cargo_id);
        if (!$user->esAdmin() && $cargo->nombre === 'ADMIN') {
            abort(403, 'Solo un administrador puede asignar el cargo ADMIN.');
        }

        if ($empleado->cargos()->where('cargo_id', $request->cargo_id)->whereNull('empleados_cargos.fecha_hasta')->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'cargo_id' => 'El empleado ya tiene ese cargo activo.',
            ]);
        }

        $empleado->cargos()->attach($request->cargo_id, ['fecha_desde' => now()->toDateString()]);

        return back();
    }

    public function desasignarCargo(Request $request, Empleado $empleado, Cargo $cargo)
    {
        $user = $request->user();

        if (!$user->esAdmin() && $user->empleado?->sucursal_id !== $empleado->sucursal_id) {
            abort(403, 'Solo podés gestionar empleados de tu sucursal.');
        }

        if (!$user->esAdmin() && $cargo->nombre === 'ADMIN') {
            abort(403, 'Solo un administrador puede remover el cargo ADMIN.');
        }

        $tieneCargoActivo = $empleado->cargos()
            ->wherePivot('cargo_id', $cargo->id)
            ->whereNull('empleados_cargos.fecha_hasta')
            ->exists();

        if ($tieneCargoActivo) {
            $empleado->cargos()->updateExistingPivot($cargo->id, ['fecha_hasta' => now()->toDateString()]);
        }

        return back();
    }
}
