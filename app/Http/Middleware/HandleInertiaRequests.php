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
                'empleado.cargos'          => fn($q) => $q->whereNull('empleados_cargos.fecha_hasta'),
                'empleado.cargos.permisos' => fn($q) => $q->where('activo', true),
                'empleado.sucursal',
            ]);
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user'      => $user?->only(['id', 'name', 'apellido', 'email', 'created_at', 'dni', 'telefono']),
                'empleado'  => $user?->empleado ? [
                    'sucursal_id' => $user->empleado->sucursal_id,
                    'sucursal' => $user->empleado->sucursal ? $user->empleado->sucursal->only(['id', 'nombre']) : null,
                ] : null,
                'permisos'  => $user?->getPermisosActivos() ?? [],
                'esAdmin'   => $user?->esAdmin() ?? false,
                'esGerente' => $user?->esGerente() ?? false,
                'unreadNotificationsCount' => $user ? $user->unreadNotifications()->count() : 0,
            ],
            'unreadNotificationsCount' => $user ? $user->unreadNotifications()->count() : 0,
            'carritoCount' => CarritoController::getCount(),
            'carritoTotal' => CarritoController::getTotal(),
            'globalCategorias' => \App\Models\Categoria::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'globalEditoriales' => \App\Models\Proveedor::where('activo', true)->whereHas('libroMasters', fn($q) => $q->where('activo', true))->orderBy('nombre_empresa')->get(['id', 'nombre_empresa']),
            'globalMangaEditoriales' => \App\Models\Proveedor::where('activo', true)->whereHas('libroMasters', fn($q) => $q->where('activo', true)->whereHas('categoria', fn($c) => $c->whereRaw('LOWER(nombre) LIKE ?', ['%manga%'])))->orderBy('nombre_empresa')->get(['id', 'nombre_empresa']),
            'globalComicEditoriales' => \App\Models\Proveedor::where('activo', true)->whereHas('libroMasters', fn($q) => $q->where('activo', true)->whereHas('categoria', fn($c) => $c->whereRaw('LOWER(nombre) NOT LIKE ?', ['%manga%'])))->orderBy('nombre_empresa')->get(['id', 'nombre_empresa']),
            'flash' => [
                'success' => session('success') ?? session('message'),
                'warning' => session('warning'),
                'error'   => session('error'),
                'error_modal' => session('error_modal'),
                'nuevaPassword' => session('nuevaPassword'),
            ],
        ];
    }
}
