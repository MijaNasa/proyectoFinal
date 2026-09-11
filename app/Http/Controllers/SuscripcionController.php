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
        // Top 5 series con más suscripciones activas
        $topSeries = Suscripcion::select('libro_master_id', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->where('estado', 'activa')
            ->groupBy('libro_master_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with(['serie' => fn($q) => $q->select('id', 'titulo', 'portada')->with(['libros' => fn($l) => $l->select('id', 'master_id', 'portada', 'numero_tomo')->whereNotNull('portada')->where('portada', '!=', '')])])
            ->get();

        // Listado de suscripciones
        $query = Suscripcion::with(['cliente.user:id,name,apellido,email', 'serie:id,titulo', 'sucursal:id,nombre']);

        if ($request->filled('search')) {
            $like = '%' . mb_strtolower($request->search) . '%';
            $query->whereHas('cliente.user', function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(apellido) LIKE ?', [$like]);
            })->orWhereHas('serie', function ($q) use ($like) {
                $q->whereRaw('LOWER(titulo) LIKE ?', [$like]);
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $suscripciones = $query->latest()->paginate(15)->withQueryString();

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
                    ->whereIn('estado', ['activa', 'pausada'])
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
            'suscripciones' => $suscripciones,
            'topSeries'     => $topSeries,
            'clientes'      => $clientes,
            'libro_masters' => $libroMasters,
            'sucursales'    => $sucursales,
            'filters'       => $request->only(['search', 'estado'])
        ]);
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

        $exists = Suscripcion::where('cliente_id', $request->cliente_id)
            ->where('libro_master_id', $request->libro_master_id)
            ->whereIn('estado', ['activa', 'pausada'])
            ->first();

        if ($exists) {
            return back()->withErrors(['libro_master_id' => 'El cliente ya se encuentra suscrito a esta serie.']);
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
            'estado'      => ['sometimes', Rule::in(['activa', 'pausada'])],
            'tomo_inicio' => ['nullable', 'integer', 'min:1'],
        ]);

        $suscripcion->update($request->only(['estado', 'tomo_inicio']));

        return back()->with('success', 'Suscripción actualizada exitosamente.');
    }

    public function destroy(Suscripcion $suscripcion)
    {
        $suscripcion->delete();
        return back()->with('success', 'Suscripción eliminada exitosamente.');
    }
}
