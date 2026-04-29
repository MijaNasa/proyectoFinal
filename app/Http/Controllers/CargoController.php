<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\Permiso;
use App\Http\Requests\StoreCargoRequest;
use App\Http\Requests\UpdateCargoRequest;

class CargoController extends Controller
{
    public function index()
    {
        $cargos = Cargo::with(['permisos' => fn($q) => $q->where('activo', true)])
            ->withCount(['empleados as empleados_activos_count' => fn($q) => $q->wherePivotNull('fecha_hasta')])
            ->orderBy('nombre')
            ->get();

        $permisos = Permiso::where('activo', true)
            ->orderBy('modulo')
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'modulo']);

        return inertia('Cargos/Index', [
            'cargos'   => $cargos,
            'permisos' => $permisos,
        ]);
    }

    public function store(StoreCargoRequest $request)
    {
        $cargo = Cargo::create([
            'nombre'      => strtoupper($request->nombre),
            'descripcion' => $request->descripcion,
            'activo'      => true,
        ]);

        if ($request->filled('permiso_ids')) {
            $cargo->permisos()->sync($request->permiso_ids);
        }

        return redirect()->route('cargos.index')
            ->with('message', 'Cargo creado con éxito');
    }

    public function update(UpdateCargoRequest $request, Cargo $cargo)
    {
        $cargo->update([
            'nombre'      => strtoupper($request->nombre),
            'descripcion' => $request->descripcion,
            'activo'      => $request->boolean('activo', true),
        ]);

        $cargo->permisos()->sync($request->permiso_ids ?? []);

        return redirect()->route('cargos.index')
            ->with('message', 'Cargo actualizado con éxito');
    }

    public function destroy(Cargo $cargo)
    {
        $cargo->update(['activo' => false]);

        return redirect()->route('cargos.index')
            ->with('message', 'Cargo desactivado');
    }
}
