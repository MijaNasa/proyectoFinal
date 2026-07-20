<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\ParadaReparto;
use App\Models\RutaReparto;
use App\Models\Venta;
use App\Http\Requests\StoreRutaRepartoRequest;
use App\Http\Requests\UpdateRutaRepartoRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RutaRepartoController extends Controller
{
    public function index(Request $request)
    {
        $query = RutaReparto::with(['repartidor.user', 'paradas']);

        if ($request->filled('search')) {
            $query->where('nombre', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('fecha')) {
            $query->where('fecha', $request->fecha);
        }

        $rutas = $query->latest('fecha')->paginate(10)->withQueryString();

        $stats = [
            'total'          => RutaReparto::count(),
            'activas'        => RutaReparto::where('activa', true)->count(),
            'paradas_hoy'    => ParadaReparto::whereHas('ruta', fn($q) => $q->where('fecha', today()))->count(),
            'entregadas_hoy' => ParadaReparto::where('estado', 'entregada')
                                    ->whereHas('ruta', fn($q) => $q->where('fecha', today()))->count(),
        ];

        return inertia('Repartos/Index', [
            'rutas'        => $rutas,
            'repartidores' => Empleado::with('user:id,name,apellido')->whereHas('cargos', function($q) {
                $q->where('nombre', 'REPARTIDOR');
            })->get(['id', 'user_id']),
            'stats'        => $stats,
            'filters'      => $request->only(['search', 'fecha']),
        ]);
    }

    public function store(StoreRutaRepartoRequest $request)
    {
        $ruta = RutaReparto::create($request->validated());

        return redirect()->route('rutas-reparto.show', $ruta)
            ->with('message', 'Ruta de reparto creada');
    }

    public function show(RutaReparto $rutasReparto)
    {
        $rutasReparto->load([
            'repartidor.user',
            'paradas' => fn($q) => $q->orderBy('orden'),
            'paradas.venta.cliente.user',
            'paradas.venta.detalles.libro.master',
        ]);

        // Ventas online con envío a domicilio (o sin tipo_envio asignado), no asignadas a esta ruta
        $ventasDisponibles = Venta::with(['cliente.user', 'detalles.libro.master'])
            ->where('tipo', 'online')
            ->where('tipo_envio', 'domicilio')
            ->whereNotNull('direccion_envio')
            ->whereIn('estado', ['en_preparacion'])
            ->whereDoesntHave('paradas', fn($q) => $q->where('ruta_reparto_id', $rutasReparto->id))
            ->latest()
            ->get();

        return inertia('Repartos/Show', [
            'ruta'               => $rutasReparto,
            'repartidores'       => Empleado::with('user:id,name,apellido')->whereHas('cargos', function($q) {
                $q->where('nombre', 'REPARTIDOR');
            })->get(['id', 'user_id']),
            'ventas_disponibles' => $ventasDisponibles,
        ]);
    }

    public function update(UpdateRutaRepartoRequest $request, RutaReparto $rutasReparto)
    {
        $rutasReparto->update($request->validated());

        return back()->with('message', 'Ruta actualizada');
    }

    public function destroy(RutaReparto $rutasReparto)
    {
        if ($rutasReparto->paradas()->where('estado', 'entregada')->exists()) {
            return back()->with('error', 'No se puede eliminar una ruta con paradas ya entregadas.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($rutasReparto) {
            foreach ($rutasReparto->paradas as $parada) {
                if ($parada->venta && $parada->venta->estado === 'enviado') {
                    $parada->venta->update(['estado' => 'en_preparacion']);
                }
            }
            $rutasReparto->delete();
        });

        return redirect()->route('rutas-reparto.index')
            ->with('message', 'Ruta eliminada. Las ventas volvieron a estar en preparación.');
    }

    public function asignarVenta(Request $request, RutaReparto $rutasReparto)
    {
        $request->validate([
            'venta_ids'      => 'required|array|min:1',
            'venta_ids.*'    => 'exists:ventas,id',
            'observaciones'  => 'nullable|string|max:500',
        ]);

        $agregadas = 0;

        DB::transaction(function () use ($request, $rutasReparto, &$agregadas) {
            // Lock the ruta to serialize concurrent asignarVenta calls on la misma ruta
            RutaReparto::lockForUpdate()->find($rutasReparto->id);

            foreach ($request->venta_ids as $ventaId) {
                $venta = Venta::find($ventaId);

                if (!$venta || $venta->tipo_envio !== 'domicilio' || $venta->estado === 'pendiente_pago') {
                    continue;
                }

                // Una venta solo puede estar en una ruta activa a la vez
                $yaAsignada = ParadaReparto::where('venta_id', $venta->id)
                    ->whereIn('estado', ['pendiente', 'en camino', 'entregada'])
                    ->exists();

                if ($yaAsignada) {
                    continue;
                }

                $orden = ($rutasReparto->paradas()->max('orden') ?? 0) + 1;

                $rutasReparto->paradas()->create([
                    'venta_id'      => $venta->id,
                    'estado'        => 'pendiente',
                    'latitud'       => $venta->latitud,
                    'longitud'      => $venta->longitud,
                    'orden'         => $orden,
                    'observaciones' => $request->observaciones,
                ]);

                $agregadas++;
            }
        });

        if ($agregadas === 0) {
            return back()->with('error', 'Ninguna de las ventas seleccionadas pudo agregarse (ya asignadas o no válidas).');
        }

        return back()->with('message', $agregadas === 1 ? 'Venta agregada a la ruta' : "$agregadas entregas agregadas a la ruta");
    }


    public function removeParada(RutaReparto $rutasReparto, ParadaReparto $parada)
    {
        if ($parada->ruta_reparto_id !== $rutasReparto->id) {
            abort(404);
        }

        DB::transaction(function () use ($rutasReparto, $parada) {
            $fresh = ParadaReparto::lockForUpdate()->find($parada->id);

            if ($fresh->estado === 'entregada') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'estado' => 'No se puede quitar una parada ya entregada.',
                ]);
            }

            if ($fresh->venta && $fresh->venta->estado === 'enviado') {
                $fresh->venta->update(['estado' => 'en_preparacion']);
            }

            $fresh->delete();

            // Renumerar orden
            $rutasReparto->paradas()->orderBy('orden')->each(function ($p, $i) {
                $p->update(['orden' => $i + 1]);
            });
        });

        return back()->with('message', 'Parada eliminada de la ruta');
    }

    public function actualizarEstadoParada(Request $request, RutaReparto $rutasReparto, ParadaReparto $parada)
    {
        if ($parada->ruta_reparto_id !== $rutasReparto->id) {
            abort(404);
        }

        $request->validate([
            'estado'        => 'required|in:pendiente,en camino,entregada,fallida',
            'observaciones' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($request, $parada) {
            $fresh = ParadaReparto::lockForUpdate()->find($parada->id);

            if ($fresh->estado === 'entregada') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'estado' => 'No se puede cambiar el estado de una parada ya entregada.',
                ]);
            }

            $fresh->update([
                'estado'        => $request->estado,
                'observaciones' => $request->observaciones ?? $fresh->observaciones,
            ]);

            // Sincronizar estado de la venta
            $estadoVenta = match ($request->estado) {
                'en camino'  => 'enviado',
                'entregada'  => 'finalizado',
                'fallida'    => 'en_preparacion',
                default      => null,
            };

            if ($estadoVenta && $fresh->venta) {
                $fresh->venta->update(['estado' => $estadoVenta]);
            }
        });

        return back()->with('message', 'Estado actualizado');
    }

    public function optimizarRuta(RutaReparto $rutasReparto)
    {
        $paradas = $rutasReparto->paradas()->orderBy('orden')->get();
        $conCoordenadas = $paradas->filter(fn($p) => $p->latitud && $p->longitud)->values();

        if ($conCoordenadas->isEmpty()) {
            return back()->with('error', 'No hay paradas con coordenadas válidas para optimizar.');
        }

        // Origen: San Martin 843, Rosario
        $startLat = -32.9473682;
        $startLon = -60.6364222;

        $unvisited = $conCoordenadas->toArray();
        $ordenadas = [];

        $currentLat = (float) $startLat;
        $currentLon = (float) $startLon;

        // Nearest Neighbor (Greedy TSP)
        while (!empty($unvisited)) {
            $nearestIdx = -1;
            $minDist = PHP_FLOAT_MAX;

            foreach ($unvisited as $idx => $p) {
                // Distancia euclidiana
                $dist = pow(((float)$p['latitud'] - $currentLat), 2) + pow(((float)$p['longitud'] - $currentLon), 2);
                if ($dist < $minDist) {
                    $minDist = $dist;
                    $nearestIdx = $idx;
                }
            }

            $nearest = $unvisited[$nearestIdx];
            $ordenadas[] = $nearest;
            $currentLat = (float) $nearest['latitud'];
            $currentLon = (float) $nearest['longitud'];
            
            unset($unvisited[$nearestIdx]);
        }

        $idsOrdenadas = collect($ordenadas)->pluck('id')->toArray();
        $resto = $paradas->filter(fn($p) => !in_array($p->id, $idsOrdenadas))->values();

        \Illuminate\Support\Facades\DB::transaction(function () use ($ordenadas, $resto) {
            $orden = 1;
            foreach ($ordenadas as $p) {
                \App\Models\ParadaReparto::where('id', $p['id'])->update(['orden' => $orden++]);
            }
            foreach ($resto as $p) {
                \App\Models\ParadaReparto::where('id', $p->id)->update(['orden' => $orden++]);
            }
        });

        return back()->with('message', 'Ruta optimizada automáticamente por proximidad.');
    }

    public function iniciarRuta(RutaReparto $rutasReparto)
    {
        if ($rutasReparto->activa) {
            return back()->with('error', 'La ruta ya se encuentra activa.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($rutasReparto) {
            $rutasReparto->update(['activa' => true]);

            foreach ($rutasReparto->paradas as $parada) {
                if ($parada->estado === 'pendiente') {
                    $parada->update(['estado' => 'en camino']);
                    if ($parada->venta) {
                        $parada->venta->update(['estado' => 'enviado']);
                    }
                }
            }
        });

        return back()->with('message', 'Ruta iniciada. Las ventas ahora están en camino.');
    }
}
