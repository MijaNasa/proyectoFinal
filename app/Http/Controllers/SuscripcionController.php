<?php

namespace App\Http\Controllers;

use App\Models\Suscripcion;
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

        return inertia('Suscripciones/Index', [
            'suscripciones' => $suscripciones,
            'topSeries' => $topSeries,
            'filters' => $request->only(['search', 'estado'])
        ]);
    }
    public function store(Request $request)
    {
        $request->validate([
            'cliente_id'      => 'required|exists:clientes,id',
            'libro_master_id' => 'required|exists:libro_masters,id',
            'sucursal_id'     => 'required|exists:sucursales,id',
            'tomo_inicio'     => 'nullable|integer|min:1',
        ]);

        $exists = Suscripcion::where('cliente_id', $request->cliente_id)
            ->where('libro_master_id', $request->libro_master_id)
            ->where('sucursal_id', $request->sucursal_id)
            ->first();

        if ($exists) {
            return back()->withErrors(['libro_master_id' => 'El cliente ya está suscrito a esta serie en esta sucursal.']);
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
