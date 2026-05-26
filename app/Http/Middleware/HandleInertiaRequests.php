<?php

namespace App\Http\Middleware;

use App\Http\Controllers\CarritoController;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user) {
            $user->load([
                'empleado.cargos'          => fn($q) => $q->wherePivotNull('fecha_hasta'),
                'empleado.cargos.permisos' => fn($q) => $q->where('activo', true),
            ]);
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user'      => $user?->only(['id', 'name', 'apellido', 'email']),
                'permisos'  => $user?->getPermisosActivos() ?? [],
                'esAdmin'   => $user?->esAdmin() ?? false,
                'esGerente' => $user?->esGerente() ?? false,
            ],
            'carritoCount' => CarritoController::getCount(),
            'flash' => [
                'success' => session('success'),
                'warning' => session('warning'),
                'error'   => session('error'),
            ],
        ];
    }
}
