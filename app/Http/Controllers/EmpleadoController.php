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
     * Recursos Humanos es solo para Admin y Gerente. Vendedores, Repartidores
     * y cualquier otro cargo no deben poder gestionar empleados aunque el
     * permiso dinamico 'empleados.acceder' llegara a estar mal configurado.
     */
    private function verificarAccesoRH(\App\Models\User $user): void
    {
        if (!$user->esAdmin() && !$user->esGerente()) {
            abort(403, 'Recursos Humanos es exclusivo de Administradores y Gerentes.');
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $this->verificarAccesoRH($user);

        $sucursalId = $user->sucursalRestringidaId();

        $estado = $request->get('estado', 'activos'); // activos | inactivos | bajas | todos

        $query = Empleado::query()->with([
            'user',
            'sucursal',
            'cargos' => fn($q) => $q->whereNull('empleados_cargos.fecha_hasta'),
        ])
            // Un Gerente solo ve el personal de su propia sucursal. Admin ve todo.
            ->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId));

        if ($estado === 'bajas' || $estado === 'inactivos') {
            $query->onlyTrashed();
        } elseif ($estado === 'todos') {
            $query->withTrashed();
        }

        if ($request->has('search') && filled($request->search)) {
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

        $filterCargoId = $request->input('cargo_id');
        if (filled($filterCargoId)) {
            $query->whereHas('cargos', function ($q) use ($filterCargoId) {
                $q->where('cargo_id', $filterCargoId)->whereNull('empleados_cargos.fecha_hasta');
            });
        }

        // El filtro de sucursal solo tiene sentido para Admin: un Gerente ya
        // esta restringido a la suya, filtrar no cambiaria nada.
        $filterSucursalId = $user->esAdmin() ? $request->input('sucursal_id') : null;
        if (filled($filterSucursalId)) {
            $query->where('sucursal_id', $filterSucursalId);
        }

        $empleados = $query->latest()->paginate(10)->withQueryString();

        $statsBase = Empleado::query()->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId));
        $stats = [
            'total_activos'   => (clone $statsBase)->count(),
            'total_inactivos' => (clone $statsBase)->onlyTrashed()->count(),
            'total_bajas'     => (clone $statsBase)->onlyTrashed()->count(),
            'total_todos'     => (clone $statsBase)->withTrashed()->count(),
        ];

        return inertia('Empleados/Index', [
            'empleados'  => $empleados,
            'sucursales' => \App\Models\Sucursal::where('activo', true)->get(['id', 'nombre', 'es_principal']),
            'cargos'     => Cargo::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'filters'    => [
                'search'      => $request->search ?? '',
                'estado'      => $estado,
                'cargo_id'    => $filterCargoId ?? '',
                'sucursal_id' => $filterSucursalId ?? '',
            ],
            'stats'      => $stats,
            'miSucursalId' => $sucursalId,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmpleadoRequest $request)
    {
        $userAuth = $request->user();
        $this->verificarAccesoRH($userAuth);

        if ($request->filled('cargo_id')) {
            $cargoSolicitado = Cargo::find($request->cargo_id);
            if ($cargoSolicitado && $cargoSolicitado->nombre === 'ADMIN' && !$userAuth->esAdmin()) {
                abort(403, 'Solo un administrador puede asignar el cargo ADMIN.');
            }
        }

        \DB::transaction(function() use ($request, $userAuth) {
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

            // Un Gerente solo puede dar de alta empleados en su propia sucursal.
            // Los repartidores no estan forzados a ninguna sucursal fija: se
            // respeta lo que se elija (el frontend ya sugiere Central por
            // defecto al asignarles ese cargo, pero se puede cambiar).
            $sucursalId = $userAuth->esAdmin() ? $request->sucursal_id : $userAuth->empleado?->sucursal_id;

            $empleado = $user->empleado()->create([
                'legajo' => $legajo,
                'sucursal_id' => $sucursalId,
                'fecha_ingreso' => $request->fecha_ingreso,
            ]);

            if ($request->filled('cargo_id')) {
                $empleado->cargos()->attach($request->cargo_id, [
                    'fecha_desde' => $request->fecha_ingreso,
                ]);
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
        $userAuth = $request->user();
        $this->verificarAccesoRH($userAuth);

        if (!$userAuth->esAdmin() && $userAuth->empleado?->sucursal_id !== $empleado->sucursal_id) {
            abort(403, 'Solo podés editar empleados de tu sucursal.');
        }

        if ($request->filled('cargo_id')) {
            $cargoSolicitado = Cargo::find($request->cargo_id);
            if ($cargoSolicitado && $cargoSolicitado->nombre === 'ADMIN' && !$userAuth->esAdmin()) {
                abort(403, 'Solo un administrador puede asignar el cargo ADMIN.');
            }
        }

        \DB::transaction(function() use ($request, $empleado) {
            $empleado->user->update([
                'name' => $request->name,
                'apellido' => $request->apellido,
                'dni' => $request->dni,
                'email' => $request->email,
                'telefono' => $request->telefono,
            ]);

            // Los repartidores ya no estan forzados a Central: se respeta la
            // sucursal elegida en el formulario, sea cual sea el cargo.
            $empleado->update([
                'legajo' => $request->legajo,
                'sucursal_id' => $request->sucursal_id,
                'fecha_ingreso' => $request->fecha_ingreso,
            ]);

            if ($request->filled('cargo_id')) {
                $cargoActual = $empleado->cargos()
                    ->whereNull('empleados_cargos.fecha_hasta')
                    ->first();

                $newCargoId = (int)$request->cargo_id;

                if (!$cargoActual || $cargoActual->id !== $newCargoId) {
                    if ($cargoActual) {
                        $empleado->cargos()->updateExistingPivot($cargoActual->id, [
                            'fecha_hasta' => now()->toDateString(),
                        ]);
                    }

                    $yaAsignadoPreviamente = $empleado->cargos()
                        ->wherePivot('cargo_id', $newCargoId)
                        ->whereNull('empleados_cargos.fecha_hasta')
                        ->exists();

                    if (!$yaAsignadoPreviamente) {
                        $empleado->cargos()->attach($newCargoId, ['fecha_desde' => now()->toDateString()]);
                    }
                }
            }
        });

        return redirect()->route('empleados.index')
            ->with('message', 'Empleado actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage (Baja lógica).
     */
    public function destroy(Empleado $empleado)
    {
        $userAuth = request()->user();
        $this->verificarAccesoRH($userAuth);

        if (!$userAuth->esAdmin() && $userAuth->empleado?->sucursal_id !== $empleado->sucursal_id) {
            abort(403, 'Solo podés dar de baja a empleados de tu sucursal.');
        }

        if ($empleado->user_id === $userAuth->id) {
            return redirect()->route('empleados.index')
                ->with('error_modal', 'No podés darte de baja a vos mismo.');
        }

        $tieneRutasActivas = \App\Models\RutaReparto::where('repartidor_id', $empleado->id)
            ->where('estado', '!=', 'finalizada')
            ->exists();

        if ($tieneRutasActivas) {
            return redirect()->route('empleados.index')
                ->with('error_modal', 'No se puede dar de baja al empleado porque tiene rutas de reparto activas asignadas.');
        }

        \DB::transaction(function() use ($empleado) {
            $user = $empleado->user;
            $empleado->update(['fecha_egreso' => now()->toDateString()]);
            $empleado->delete(); // Soft delete en empleados
            if ($user) {
                $user->update(['activo' => false]);
            }
        });

        return redirect()->route('empleados.index')
            ->with('swal_success', 'Empleado dado de baja con éxito. Su historial y ventas se conservan intactos.');
    }

    /**
     * Reactivar un empleado dado de baja.
     */
    public function reactivar(Request $request, $id)
    {
        $empleado = Empleado::withTrashed()->findOrFail($id);
        $userAuth = $request->user();
        $this->verificarAccesoRH($userAuth);

        if (!$userAuth->esAdmin() && $userAuth->empleado?->sucursal_id !== $empleado->sucursal_id) {
            abort(403, 'Solo podés reactivar empleados de tu sucursal.');
        }

        \DB::transaction(function() use ($empleado) {
            $empleado->restore();
            $empleado->update(['fecha_egreso' => null]);
            if ($empleado->user) {
                $empleado->user->update(['activo' => true]);
            }
        });

        return redirect()->route('empleados.index')
            ->with('swal_success', 'Empleado reactivado con éxito.');
    }

    public function resetearPassword(Request $request, Empleado $empleado)
    {
        $user = $request->user();
        $this->verificarAccesoRH($user);

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
        $this->verificarAccesoRH($user);

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
        $this->verificarAccesoRH($user);

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
