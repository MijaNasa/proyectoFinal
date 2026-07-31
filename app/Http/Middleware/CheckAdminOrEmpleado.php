<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminOrEmpleado
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || (!$user->esAdmin() && !$user->empleado)) {
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                abort(403, 'Acceso denegado. Se requiere perfil de administrador o empleado.');
            }
            return redirect()->route('mi-cuenta.index');
        }

        return $next($request);
    }
}
