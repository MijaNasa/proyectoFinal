<?php

namespace App\Http\Controllers;

use App\Models\Suscripcion;
use App\Models\Cliente;
use App\Models\LibroMaster;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SuscripcionController extends Controller
{
    public function index(Request $request)
    {
        // Asegurar que la columna deleted_at exista en la BD (resiliente ante migraciones pendientes en PostgreSQL/Render)
        if (!\Illuminate\Support\Facades\Schema::hasColumn('suscripcions', 'deleted_at')) {
            try {
                \Illuminate\Support\Facades\Schema::table('suscripcions', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->softDeletes();
                });
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Verificación deleted_at en suscripcions: ' . $e->getMessage());
            }
        }

        try {
            // Top 5 series con más suscripciones activas (ANSI SQL estándar compatible con PostgreSQL)
            $topSeries = Suscripcion::select('libro_master_id', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
                ->where('estado', 'activa')
                ->groupBy('libro_master_id')
                ->orderByRaw('count(*) desc')
                ->limit(5)
                ->with(['serie:id,titulo,portada'])
                ->get();

            $search = $request->input('search');

            // Series agrupadas con sus suscripciones
            $query = LibroMaster::query()
                ->with([
                    'autor:id,nombre,apellido',
                    'categoria:id,nombre',
                    'proveedor:id,nombre_empresa',
                    'suscripciones' => function ($sq) use ($search) {
                        $sq->with(['cliente.user:id,name,apellido,email,dni', 'sucursal:id,nombre']);
                        if ($search) {
                            $like = '%' . mb_strtolower($search) . '%';
                            $sq->where(function ($subQ) use ($like) {
                                $subQ->whereHas('cliente.user', function ($uq) use ($like) {
                                    $uq->whereRaw('LOWER(name) LIKE ?', [$like])
                                       ->orWhereRaw('LOWER(apellido) LIKE ?', [$like])
                                       ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                                       ->orWhereRaw('LOWER(dni) LIKE ?', [$like]);
                                })->orWhereHas('serie', function ($mq) use ($like) {
                                    $mq->whereRaw('LOWER(titulo) LIKE ?', [$like]);
                                });
                            });
                        }
                        $sq->latest();
                    }
                ])
                ->whereHas('suscripciones', function ($q) use ($search) {
                    if ($search) {
                        $like = '%' . mb_strtolower($search) . '%';
                        $q->where(function ($subQ) use ($like) {
                            $subQ->whereHas('cliente.user', function ($uq) use ($like) {
                                $uq->whereRaw('LOWER(name) LIKE ?', [$like])
                                   ->orWhereRaw('LOWER(apellido) LIKE ?', [$like])
                                   ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                                   ->orWhereRaw('LOWER(dni) LIKE ?', [$like]);
                            })->orWhereHas('serie', function ($mq) use ($like) {
                                $mq->whereRaw('LOWER(titulo) LIKE ?', [$like]);
                            });
                        });
                    }
                })
                ->withCount('suscripciones')
                ->orderByDesc('suscripciones_count')
                ->orderBy('titulo');

            $series = $query->paginate(12)->withQueryString();

            $clientes = Cliente::with([
                'user:id,name,apellido,email,dni',
                'suscripciones:id,cliente_id,libro_master_id,estado'
            ])
                ->whereHas('user', fn($q) => $q->where('activo', true))
                ->get()
                ->map(fn($c) => [
                    'id'                      => $c->id,
                    'nombre'                  => trim(($c->user?->name ?? '') . ' ' . ($c->user?->apellido ?? '')),
                    'email'                   => $c->user?->email ?? '',
                    'dni'                     => $c->user?->dni ?? '',
                    'suscripciones_master_ids' => $c->suscripciones
                        ->pluck('libro_master_id')
                        ->values()
                        ->all()
                ])
                ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            $libroMasters = LibroMaster::where('activo', true)
                ->orWhereNull('activo')
                ->orderBy('titulo')
                ->get(['id', 'titulo']);

            $sucursales = Sucursal::where('activo', true)->get(['id', 'nombre']);

            return inertia('Suscripciones/Index', [
                'series'        => $series,
                'topSeries'     => $topSeries,
                'clientes'      => $clientes,
                'libro_masters' => $libroMasters,
                'sucursales'    => $sucursales,
                'filters'       => $request->only(['search'])
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error en SuscripcionController@index: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
    public function store(Request $request)
    {
        $request->validate([
            'cliente_id'      => 'required|exists:clientes,id',
            'libro_master_id' => 'required|exists:libro_masters,id',
            'sucursal_id'     => 'required|exists:sucursales,id',
            'tomo_inicio'     => 'nullable|integer|min:1',
        ], [
            'cliente_id.required'      => 'Debe seleccionar un cliente.',
            'cliente_id.exists'        => 'El cliente seleccionado no es válido.',
            'libro_master_id.required' => 'Debe seleccionar una serie.',
            'libro_master_id.exists'   => 'La serie seleccionada no es válida.',
            'sucursal_id.required'     => 'Debe seleccionar una sucursal.',
            'sucursal_id.exists'       => 'La sucursal seleccionada no es válida.',
            'tomo_inicio.min'          => 'El tomo de inicio debe ser mayor o igual a 1.',
        ]);

        $existing = Suscripcion::withTrashed()
            ->where('cliente_id', $request->cliente_id)
            ->where('libro_master_id', $request->libro_master_id)
            ->first();

        if ($existing) {
            if (!$existing->trashed()) {
                return back()->withErrors(['libro_master_id' => 'El cliente ya se encuentra suscrito a esta serie.']);
            }

            // Si estaba deshabilitada (soft deleted), se reanuda el mismo registro
            $existing->restore();
            $existing->update([
                'sucursal_id' => $request->sucursal_id,
                'tomo_inicio' => $request->input('tomo_inicio', 1) ?: 1,
                'estado'      => 'activa'
            ]);

            return back()->with('success', 'Suscripción reanudada exitosamente.');
        }

        Suscripcion::create([
            'cliente_id'      => $request->cliente_id,
            'libro_master_id' => $request->libro_master_id,
            'sucursal_id'     => $request->sucursal_id,
            'tomo_inicio'     => $request->input('tomo_inicio', 1) ?: 1,
            'estado'          => 'activa'
        ]);

        return back()->with('success', 'Suscripción registrada exitosamente.');
    }

    public function update(Request $request, Suscripcion $suscripcion)
    {
        $request->validate([
            'tomo_inicio' => ['nullable', 'integer', 'min:1'],
            'sucursal_id' => ['nullable', 'exists:sucursales,id'],
        ]);

        $suscripcion->update($request->only(['tomo_inicio', 'sucursal_id']));

        return back()->with('success', 'Suscripción actualizada exitosamente.');
    }

    public function destroy(Suscripcion $suscripcion)
    {
        $suscripcion->delete();
        return back()->with('success', 'Suscripción deshabilitada exitosamente.');
    }
}
