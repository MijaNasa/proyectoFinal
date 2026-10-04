<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render (y cualquier PaaS con reverse proxy) entrega la app detrás de un
        // balanceador: sin esto, $request->ip() lee la IP interna del proxy en vez
        // de la del visitante real, y cambia en cada pedido. Rompe cualquier cosa
        // que dependa de la IP real (rate limit por IP del chatbot, throttle de
        // rutas, detección de HTTPS vía X-Forwarded-Proto).
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'checkout/webhook',
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'permiso' => \App\Http\Middleware\CheckPermiso::class,
            'admin_or_empleado' => \App\Http\Middleware\CheckAdminOrEmpleado::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response, Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->header('X-Inertia')) {
                $status = $response->getStatusCode();
                if ($status === 403) {
                    return back()->with('error', $e->getMessage() ?: 'Acceso denegado: no tienes permisos para realizar esta acción.');
                }
                if ($status === 500) {
                    \Log::error('Error 500 en petición Inertia: ' . $e->getMessage(), [
                        'url' => $request->fullUrl(),
                    ]);
                    $msg = config('app.debug') ? $e->getMessage() : 'Ocurrió un inconveniente al procesar la solicitud. Por favor intenta nuevamente.';
                    return back()->with('error', $msg);
                }
            }
            return $response;
        });
    })->create();
