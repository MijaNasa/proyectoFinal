<?php

namespace App\Http\Controllers;

use App\Models\Suscripcion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SuscripcionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'libro_master_id' => 'required|exists:libro_masters,id',
            'sucursal_id' => 'required|exists:sucursales,id',
        ]);

        $exists = Suscripcion::where('cliente_id', $request->cliente_id)
            ->where('libro_master_id', $request->libro_master_id)
            ->where('sucursal_id', $request->sucursal_id)
            ->first();

        if ($exists) {
            return back()->withErrors(['libro_master_id' => 'El cliente ya está suscrito a esta serie en esta sucursal.']);
        }

        Suscripcion::create([
            'cliente_id' => $request->cliente_id,
            'libro_master_id' => $request->libro_master_id,
            'sucursal_id' => $request->sucursal_id,
            'estado' => 'activa'
        ]);

        return back()->with('success', 'Suscripción registrada exitosamente.');
    }

    public function update(Request $request, Suscripcion $suscripcion)
    {
        $request->validate([
            'estado' => ['required', Rule::in(['activa', 'pausada'])]
        ]);

        $suscripcion->update([
            'estado' => $request->estado
        ]);

        return back()->with('success', 'Suscripción actualizada exitosamente.');
    }

    public function destroy(Suscripcion $suscripcion)
    {
        $suscripcion->delete();
        return back()->with('success', 'Suscripción eliminada exitosamente.');
    }
}
