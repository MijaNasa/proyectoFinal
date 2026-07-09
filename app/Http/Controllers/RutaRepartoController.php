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
            'repartidores' => Empleado::with('user:id,name,apellido')->get(['id', 'user_id']),
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
            ->whereIn('estado', ['en_preparacion', 'pagado'])
            ->whereDoesntHave('paradas', fn($q) => $q->where('ruta_reparto_id', $rutasReparto->id))
            ->latest()
            ->get();

        return inertia('Repartos/Show', [
            'ruta'               => $rutasReparto,
            'repartidores'       => Empleado::with('user:id,name,apellido')->get(['id', 'user_id']),
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

        $rutasReparto->delete();

        return redirect()->route('rutas-reparto.index')
            ->with('message', 'Ruta eliminada');
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

                if (!$venta || $venta->tipo !== 'online' || $venta->tipo_envio === 'retiro') {
                    continue;
                }

                // Una venta solo puede estar en una ruta activa a la vez
                $yaAsignada = ParadaReparto::where('venta_id', $venta->id)
                    ->whereIn('estado', ['pendiente', 'en camino', 'entregada'])
                    ->exists();

                if ($yaAsignada) {
                    continue;
                }

                [$latitud, $longitud] = $this->geocodificar($venta->direccion_envio);

                $orden = ($rutasReparto->paradas()->max('orden') ?? 0) + 1;

                $rutasReparto->paradas()->create([
                    'venta_id'      => $venta->id,
                    'estado'        => 'pendiente',
                    'latitud'       => $latitud,
                    'longitud'      => $longitud,
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

    /**
     * Geocodifica una dirección usando la API de Google Maps.
     * Si no hay API key configurada o falla la consulta, devuelve coordenadas nulas
     * (la parada queda sin ubicación y se puede cargar/optimizar manualmente después).
     */
    private function geocodificar(?string $direccion): array
    {
        $apiKey = env('GOOGLE_MAPS_API_KEY') ?: env('VITE_GOOGLE_MAPS_API_KEY');

        if (!$apiKey || !$direccion) {
            return [null, null];
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $direccion,
                'key'     => $apiKey,
            ]);

            $location = $response->json('results.0.geometry.location');

            return $location ? [$location['lat'], $location['lng']] : [null, null];
        } catch (\Throwable $e) {
            return [null, null];
        }
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
                'entregada'  => 'entregado',
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
        $paradas = $rutasReparto->paradas()->with('venta')->orderBy('orden')->get();

        $conCoordenadas = $paradas->filter(fn($p) => $p->latitud && $p->longitud);

        if ($conCoordenadas->count() < 2) {
            return back()->with('error', 'Se necesitan al menos 2 paradas con coordenadas para optimizar.');
        }

        // Nearest-neighbor desde la primera parada
        $restantes  = $conCoordenadas->values()->toArray();
        $ordenadas  = [array_shift($restantes)];

        while (count($restantes) > 0) {
            $ultima   = end($ordenadas);
            $minDist  = PHP_FLOAT_MAX;
            $minIdx   = 0;

            foreach ($restantes as $idx => $parada) {
                $dist = $this->distanciaHaversine(
                    $ultima['latitud'], $ultima['longitud'],
                    $parada['latitud'], $parada['longitud']
                );
                if ($dist < $minDist) {
                    $minDist = $dist;
                    $minIdx  = $idx;
                }
            }

            $ordenadas[] = $restantes[$minIdx];
            array_splice($restantes, $minIdx, 1);
        }

        // Paradas sin coordenadas van al final
        $sinCoordenadas = $paradas->filter(fn($p) => !$p->latitud || !$p->longitud)->values();

        DB::transaction(function () use ($ordenadas, $sinCoordenadas) {
            foreach ($ordenadas as $i => $p) {
                ParadaReparto::where('id', $p['id'])->update(['orden' => $i + 1]);
            }
            foreach ($sinCoordenadas as $j => $p) {
                $p->update(['orden' => count($ordenadas) + $j + 1]);
            }
        });

        return back()->with('message', 'Ruta optimizada por cercanía');
    }

    private function distanciaHaversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
