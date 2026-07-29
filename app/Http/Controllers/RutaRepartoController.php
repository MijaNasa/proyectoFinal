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
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'like', '%' . $search . '%')
                  ->orWhereHas('repartidor.user', function($u) use ($search) {
                      $u->where('name', 'like', '%' . $search . '%')
                        ->orWhere('apellido', 'like', '%' . $search . '%');
                  });
            });
        }

        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->hasta);
        }
        if ($request->filled('fecha') && !$request->filled('desde') && !$request->filled('hasta')) {
            $query->whereDate('fecha', $request->fecha);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $rutas = $query->latest('fecha')->latest('id')->paginate(15)->withQueryString();

        return inertia('Repartos/Index', [
            'rutas'        => $rutas,
            'repartidores' => Empleado::with('user:id,name,apellido')->whereHas('cargos', function($q) {
                $q->where('nombre', 'REPARTIDOR');
            })->get(['id', 'user_id']),
            'filters'      => $request->only(['search', 'desde', 'hasta', 'fecha', 'estado']),
        ]);
    }

    public function store(StoreRutaRepartoRequest $request)
    {
        $data = $request->validated();
        $data['activa'] = false; // Las rutas siempre nacen inactivas
        $data['estado'] = 'pendiente';
        
        // Auto generar nombre "Envío 000X"
        $nextId = (RutaReparto::withTrashed()->max('id') ?? 0) + 1;
        $data['nombre'] = 'Envío ' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
        $data['fecha'] = now()->toDateString();
        
        $ruta = RutaReparto::create($data);

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
            ->whereDoesntHave('paradas', fn($q) => $q->whereIn('estado', ['en camino', 'entregada']))
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
        if ($rutasReparto->estado === 'finalizada' || $rutasReparto->paradas()->where('estado', 'entregada')->exists()) {
            return back()->with('error', 'No se puede eliminar una ruta finalizada.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($rutasReparto) {
            foreach ($rutasReparto->paradas as $parada) {
                if ($parada->venta && $parada->venta->estado === 'enviado') {
                    $parada->venta->update(['estado' => 'en_preparacion']);
                }
                $parada->delete();
            }
            $rutasReparto->delete();
        });

        return redirect()->route('rutas-reparto.index')
            ->with('message', 'Ruta eliminada. Las ventas volvieron a estar en preparación.');
    }

    public function asignarVenta(Request $request, RutaReparto $rutasReparto)
    {
        if ($rutasReparto->estado === 'finalizada') {
            return back()->with('error', 'No se pueden agregar paradas a una ruta finalizada.');
        }

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
                $paradaExistente = ParadaReparto::where('venta_id', $venta->id)
                    ->whereIn('estado', ['pendiente', 'en camino', 'entregada'])
                    ->first();

                if ($paradaExistente) {
                    if ($paradaExistente->estado === 'pendiente') {
                        // Si estaba pendiente en otra ruta (quizás olvidada), la removemos de la anterior
                        $paradaExistente->delete();
                    } else {
                        // Si ya está en camino o entregada, no la tocamos
                        continue;
                    }
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

        if (!$rutasReparto->activa) {
            return back()->with('error', 'Debes iniciar la ruta para poder cambiar el estado de las paradas.');
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

        // Verificar si quedan paradas sin finalizar
        $todasFinalizadas = !$rutasReparto->paradas()->whereIn('estado', ['pendiente', 'en camino'])->exists();
        if ($todasFinalizadas && $rutasReparto->estado !== 'finalizada') {
            $rutasReparto->update(['activa' => false, 'estado' => 'finalizada']);
            return back()->with('message', 'Estado actualizado. La ruta se ha finalizado automáticamente.');
        }

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
        if (!$rutasReparto->repartidor_id) {
            return back()->with('error', 'No se puede iniciar una ruta sin un repartidor asignado.');
        }

        if ($rutasReparto->paradas()->count() === 0) {
            return back()->with('error', 'No se puede iniciar una ruta sin paradas asignadas.');
        }

        if ($rutasReparto->activa) {
            return back()->with('error', 'La ruta ya se encuentra activa.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($rutasReparto) {
            $rutasReparto->update([
                'activa' => true,
                'estado' => 'activa',
                'fecha' => now()->toDateString() // Seteamos la fecha al momento de iniciar
            ]);

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

    public function finalizarRuta(RutaReparto $rutasReparto)
    {
        if ($rutasReparto->estado === 'finalizada') {
            return back()->with('error', 'La ruta ya se encuentra finalizada.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($rutasReparto) {
            $rutasReparto->update(['activa' => false, 'estado' => 'finalizada']);

            foreach ($rutasReparto->paradas as $parada) {
                if (in_array($parada->estado, ['pendiente', 'en camino'])) {
                    // Marcar la parada como fallida en esta ruta finalizada
                    $parada->update(['estado' => 'fallida']);
                    // Devolver la venta a en_preparacion para que pueda re-asignarse
                    if ($parada->venta) {
                        $parada->venta->update(['estado' => 'en_preparacion']);
                    }
                }
            }
        });

        return back()->with('message', 'Ruta finalizada. Las entregas pendientes se marcaron como fallidas y sus ventas volvieron a estar en preparación.');
    }

    public function reordenarParadas(Request $request, RutaReparto $rutasReparto)
    {
        $request->validate([
            'orden'   => 'required|array',
            'orden.*' => 'exists:paradas_reparto,id',
        ]);

        DB::transaction(function () use ($request, $rutasReparto) {
            foreach ($request->orden as $index => $paradaId) {
                // $index starts at 0, so order is $index + 1
                $rutasReparto->paradas()->where('id', $paradaId)->update(['orden' => $index + 1]);
            }
        });

        return back()->with('message', 'Orden actualizado correctamente.');
    }
}
