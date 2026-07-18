<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class MiCuentaController extends Controller
{
    public function index()
    {
        $pedidos = Venta::with(['detalles.libro.master:id,titulo', 'sucursal:id,nombre'])
            ->where('user_id', Auth::id())
            ->where('tipo', 'online')
            ->latest()
            ->paginate(10)
            ->through(fn($v) => [
                'id'         => $v->id,
                'fecha'      => $v->fecha,
                'total'      => $v->total,
                'estado'     => $v->estado,
                'tipo_envio'      => $v->tipo_envio,
                'sucursal_nombre' => $v->sucursal->nombre ?? 'N/A',
                'items'           => $v->detalles->map(fn($d) => [
                    'titulo'   => $d->libro->master->titulo ?? 'Libro',
                    'cantidad' => $d->cantidad,
                    'subtotal' => $d->subtotal,
                ]),
            ]);

        $user = Auth::user();

        return Inertia::render('MiCuenta/Index', [
            'pedidos' => $pedidos,
            'usuario' => [
                'name'       => $user->name,
                'apellido'   => $user->apellido,
                'email'      => $user->email,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ], [
            'current_password.required' => 'Ingresá tu contraseña actual.',
            'password.required'         => 'Ingresá la nueva contraseña.',
            'password.min'              => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.confirmed'        => 'Las contraseñas no coinciden.',
        ]);

        if (!Hash::check($request->current_password, Auth::user()->password)) {
            return back()->withErrors(['current_password' => 'La contraseña actual es incorrecta.']);
        }

        Auth::user()->update(['password' => Hash::make($request->password)]);

        return back()->with('message', 'Contraseña actualizada correctamente.');
    }
}
