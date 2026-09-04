<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermiso
{
    public function handle(Request $request, Closure $next, string $permiso): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasPermiso($permiso)) {
            if ($request->header('X-Inertia')) {
                return back()->with('error', 'Acceso denegado: no tenés permisos para realizar esta acción.');
            }
            return redirect()->route('dashboard')->with('error', 'Acceso denegado: no tenés permisos para esa sección.');
        }

        return $next($request);
    }
}
