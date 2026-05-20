<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MiCuentaController extends Controller
{
    public function index()
    {
        $pedidos = Venta::with(['detalles.libro.master'])
            ->where('user_id', Auth::id())
            ->where('tipo', 'online')
            ->latest()
            ->get()
            ->map(fn($v) => [
                'id'         => $v->id,
                'fecha'      => $v->fecha,
                'total'      => $v->total,
                'estado'     => $v->estado,
                'tipo_envio' => $v->tipo_envio,
                'items'      => $v->detalles->map(fn($d) => [
                    'titulo'   => $d->libro->master->titulo ?? 'Libro',
                    'cantidad' => $d->cantidad,
                    'subtotal' => $d->subtotal,
                ]),
            ]);

        return Inertia::render('MiCuenta/Index', [
            'pedidos' => $pedidos,
        ]);
    }
}
