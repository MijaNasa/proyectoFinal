<?php

namespace App\Http\Controllers;

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
                'direccion_envio' => $v->direccion_envio,
                'tracking_code'   => $v->tracking_code,
                'sucursal_nombre' => $v->sucursal->nombre ?? 'N/A',
                'items'           => $v->detalles->map(fn($d) => [
                    'titulo'   => ($d->libro->master->titulo ?? 'Libro') . ' - Tomo ' . ($d->libro->numero_tomo ?? 'Único'),
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

    public function uploadComprobante(Request $request, Venta $venta)
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($venta->user_id !== $user->id && $venta->cliente?->user_id !== $user->id && !$user->empleado) {
                return back()->with('error', 'No tienes permisos para modificar este pedido.');
            }
        } else {
            if ($venta->estado !== 'pendiente_pago') {
                return back()->with('error', 'No se puede subir comprobante a un pedido que no está pendiente de pago.');
            }
            if (session('checkout_venta_id') && session('checkout_venta_id') != $venta->id) {
                return back()->with('error', 'Sesión de pedido inválida.');
            }
        }

        $request->validate([
            'comprobante' => 'required|file|mimes:jpeg,png,jpg,webp,pdf|max:7168', // Max 7MB
        ], [
            'comprobante.required' => 'Seleccioná un archivo de comprobante.',
            'comprobante.file'     => 'El archivo seleccionado no es válido.',
            'comprobante.mimes'    => 'El comprobante debe ser una imagen (JPG, PNG, WEBP) o un documento PDF.',
            'comprobante.max'      => 'El archivo es demasiado grande (máximo 7 MB). Por favor elegí una imagen o captura más liviana.',
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

    public function viewComprobante(Request $request, Venta $venta)
    {
        $autorizado = false;

        if (Auth::check()) {
            $user = Auth::user();
            if ($user->esAdmin() || $user->esGerente() || $user->empleado || $venta->user_id === $user->id || $venta->cliente?->user_id === $user->id) {
                $autorizado = true;
            }
        } else {
            if (session('checkout_venta_id') == $venta->id || $request->query('ref') == $venta->id) {
                $autorizado = true;
            }
        }

        if (!$autorizado) {
            abort(403, 'No tienes permisos para ver este comprobante.');
        }

        if (!$venta->comprobante_path || !Storage::disk('public')->exists($venta->comprobante_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($venta->comprobante_path));
    }

    public function deleteComprobante(Request $request, Venta $venta)
    {
        $autorizado = false;

        if (Auth::check()) {
            $user = Auth::user();
            if ($user->esAdmin() || $user->esGerente() || $user->empleado || $venta->user_id === $user->id || $venta->cliente?->user_id === $user->id) {
                $autorizado = true;
            }
        } else {
            if (session('checkout_venta_id') == $venta->id || $request->query('ref') == $venta->id) {
                $autorizado = true;
            }
        }

        if (!$autorizado) {
            return back()->with('error', 'No tienes permisos para eliminar este comprobante.');
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
