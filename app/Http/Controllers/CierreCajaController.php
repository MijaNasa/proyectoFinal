<?php

namespace App\Http\Controllers;

use App\Models\CierreCaja;
use App\Http\Requests\StoreCierreCajaRequest;
use App\Http\Requests\UpdateCierreCajaRequest;
use Illuminate\Http\Request;

class CierreCajaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = CierreCaja::with(['sucursal', 'user']);

        $cierres = $query->latest()->paginate(10)->withQueryString();

        return inertia('Caja/Index', [
            'cierres' => $cierres,
            'sucursales' => \App\Models\Sucursal::where('activo', true)->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCierreCajaRequest $request)
    {
        $diferencia = $request->monto_real - $request->monto_esperado;

        CierreCaja::create([
            'fecha' => $request->fecha,
            'sucursal_id' => $request->sucursal_id,
            'user_id' => \Auth::id(),
            'monto_esperado' => $request->monto_esperado,
            'monto_real' => $request->monto_real,
            'diferencia' => $diferencia,
            'observaciones' => $request->observaciones,
        ]);

        return redirect()->route('cierre-cajas.index')
            ->with('message', 'Cierre de caja registrado con éxito');
    }

    public function getMontoSistema(Request $request)
    {
        $request->validate([
            'sucursal_id' => 'required|exists:sucursales,id',
            'fecha' => 'required|date',
        ]);

        // Calculamos el monto esperado:
        // Suma de transacciones en EFECTIVO (Cash) de tipo ingreso menos egreso
        $ingresos = \App\Models\Transaccion::where('sucursal_id', $request->sucursal_id)
            ->whereDate('fecha', $request->fecha)
            ->where('tipo', 'ingreso')
            ->where('metodo_pago', 'Efectivo')
            ->sum('monto');

        $egresos = \App\Models\Transaccion::where('sucursal_id', $request->sucursal_id)
            ->whereDate('fecha', $request->fecha)
            ->where('tipo', 'egreso')
            ->where('metodo_pago', 'Efectivo')
            ->sum('monto');

        return response()->json([
            'monto_sistema' => (float) ($ingresos - $egresos)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CierreCaja $cierreCaja)
    {
        $cierreCaja->delete();

        return redirect()->route('cierre-cajas.index')
            ->with('message', 'Registro de cierre eliminado');
    }
}
