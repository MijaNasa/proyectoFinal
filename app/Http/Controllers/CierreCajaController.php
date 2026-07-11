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
        $user = \Auth::user();
        if (!$user->esAdmin() && $user->empleado?->sucursal_id !== (int) $request->sucursal_id) {
            abort(403);
        }

        $yaExiste = CierreCaja::where('sucursal_id', $request->sucursal_id)
            ->where('fecha', $request->fecha)
            ->exists();

        if ($yaExiste) {
            return back()->withErrors([
                'fecha' => 'Ya existe un cierre de caja para esta sucursal en esa fecha.',
            ]);
        }

        $montoEsperado = $this->calcularMontoEsperado($request->sucursal_id, $request->fecha);
        $diferencia    = $request->monto_real - $montoEsperado;

        CierreCaja::create([
            'fecha'          => $request->fecha,
            'sucursal_id'    => $request->sucursal_id,
            'user_id'        => \Auth::id(),
            'monto_esperado' => $montoEsperado,
            'monto_real'     => $request->monto_real,
            'diferencia'     => $diferencia,
            'observaciones'  => $request->observaciones,
        ]);

        return redirect()->route('cierre-cajas.index')
            ->with('message', 'Cierre de caja registrado con éxito');
    }

    public function getMontoSistema(Request $request)
    {
        $request->validate([
            'sucursal_id' => 'required|exists:sucursales,id',
            'fecha'       => 'required|date',
        ]);

        $user = \Auth::user();
        if (!$user->esAdmin() && $user->empleado?->sucursal_id !== (int) $request->sucursal_id) {
            abort(403);
        }

        return response()->json([
            'monto_sistema' => $this->calcularMontoEsperado($request->sucursal_id, $request->fecha),
        ]);
    }

    private function calcularMontoEsperado(int|string $sucursalId, string $fecha): float
    {
        $totales = \App\Models\Transaccion::where('sucursal_id', $sucursalId)
            ->whereDate('fecha', $fecha)
            ->where('metodo_pago', 'Efectivo')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) as total_ingresos,
                COALESCE(SUM(CASE WHEN tipo = 'egreso'  THEN monto ELSE 0 END), 0) as total_egresos
            ")
            ->first();

        return (float) ($totales->total_ingresos - $totales->total_egresos);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CierreCaja $cierreCaja)
    {
        $user = \Auth::user();
        if (!$user->esAdmin() && !$user->esGerente()) {
            abort(403, 'Sólo los administradores pueden reabrir una caja.');
        }

        $cierreCaja->delete();

        return redirect()->route('cierre-cajas.index')
            ->with('message', 'Cierre eliminado correctamente. Caja reabierta.');
    }

    public function auditoria(Request $request, CierreCaja $cierreCaja)
    {
        $user = \Auth::user();
        if (!$user->esAdmin() && $user->empleado?->sucursal_id !== $cierreCaja->sucursal_id) {
            abort(403);
        }

        $transacciones = \App\Models\Transaccion::with('user:id,name')
            ->where('sucursal_id', $cierreCaja->sucursal_id)
            ->whereDate('fecha', $cierreCaja->fecha)
            ->get();

        $totalesMetodo = [
            'Efectivo' => 0,
            'Tarjeta' => 0,
            'Transferencia' => 0,
            'Cuenta Corriente' => 0,
        ];

        $movimientosManuales = [];

        foreach ($transacciones as $t) {
            $signo = $t->tipo === 'ingreso' ? 1 : -1;

            if (array_key_exists($t->metodo_pago, $totalesMetodo)) {
                $totalesMetodo[$t->metodo_pago] += ($t->monto * $signo);
            } else {
                $totalesMetodo[$t->metodo_pago] = ($t->monto * $signo);
            }

            if (!str_starts_with($t->descripcion, '[Venta #')) {
                $movimientosManuales[] = [
                    'hora'        => \Carbon\Carbon::parse($t->fecha)->format('H:i:s'),
                    'tipo'        => $t->tipo,
                    'monto'       => $t->monto,
                    'metodo_pago' => $t->metodo_pago,
                    'descripcion' => $t->descripcion,
                    'usuario'     => $t->user?->name ?? 'Sistema',
                ];
            }
        }

        return response()->json([
            'cierre'               => $cierreCaja,
            'totales_metodo'       => $totalesMetodo,
            'movimientos_manuales' => $movimientosManuales,
        ]);
    }
}
