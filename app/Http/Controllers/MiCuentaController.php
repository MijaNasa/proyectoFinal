<?php

namespace App\Http\Controllers;

use App\Models\ChatConversacion;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
                'estado'          => $v->estado,
                'metodo_pago'     => $v->metodo_pago,
                'comprobante_path'=> $v->comprobante_path ? Storage::url($v->comprobante_path) : null,
                'tipo_envio'      => $v->tipo_envio,
                'sucursal_nombre' => $v->sucursal->nombre ?? 'N/A',
                'items'           => $v->detalles->map(fn($d) => [
                    'titulo'   => ($d->libro->master->titulo ?? 'Libro') . ' - Tomo ' . ($d->libro->numero_tomo ?? 'Único'),
                    'cantidad' => $d->cantidad,
                    'subtotal' => $d->subtotal,
                ]),
            ]);

        $user = Auth::user();

        $conversacion = ChatConversacion::where('user_id', $user->id)->first();

        $chatMensajes = $conversacion
            ? $conversacion->mensajes()->orderBy('created_at')->limit(50)->get(['role', 'content', 'created_at'])
            : collect();

        return Inertia::render('MiCuenta/Index', [
            'pedidos' => $pedidos,
            'usuario' => [
                'name'       => $user->name,
                'apellido'   => $user->apellido,
                'email'      => $user->email,
                'created_at' => $user->created_at,
            ],
            'chatMensajes' => $chatMensajes,
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

    public function uploadComprobante(Request $request, Venta $venta)
    {
        if ($venta->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'comprobante' => 'required|image|mimes:jpeg,png,jpg|max:5120', // Max 5MB
        ]);

        if ($request->hasFile('comprobante')) {
            // Delete old file if exists
            if ($venta->comprobante_path) {
                Storage::disk('public')->delete($venta->comprobante_path);
                
                \Illuminate\Notifications\DatabaseNotification::where('type', \App\Notifications\ComprobanteSubido::class)
                    ->where('data', 'like', '%"url":"/ventas?search=' . $venta->id . '"%')
                    ->delete();
            }

            $path = $request->file('comprobante')->store('comprobantes', 'public');
            $venta->update(['comprobante_path' => $path]);

            // Notify employees
            $users = \App\Models\User::whereHas('empleado')->get();
            foreach ($users as $user) {
                $user->notify(new \App\Notifications\ComprobanteSubido($venta));
            }
        }

        return back()->with('message', 'Comprobante subido exitosamente. Un empleado verificará el pago a la brevedad.');
    }

    public function viewComprobante(Venta $venta)
    {
        if ($venta->user_id !== Auth::id() && !Auth::user()->esAdmin() && !Auth::user()->esGerente()) {
            abort(403);
        }

        if (!$venta->comprobante_path || !Storage::disk('public')->exists($venta->comprobante_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($venta->comprobante_path));
    }

    public function deleteComprobante(Venta $venta)
    {
        if ($venta->user_id !== Auth::id()) {
            abort(403);
        }

        if ($venta->comprobante_path) {
            Storage::disk('public')->delete($venta->comprobante_path);
            $venta->update(['comprobante_path' => null]);
            
            // Eliminar notificación vieja
            \Illuminate\Notifications\DatabaseNotification::where('type', \App\Notifications\ComprobanteSubido::class)
                ->where('data', 'like', '%"url":"/ventas?search=' . $venta->id . '"%')
                ->delete();
        }

        return back()->with('message', 'Comprobante eliminado exitosamente.');
    }
}
