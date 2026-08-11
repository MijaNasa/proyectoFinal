<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Libro;
use App\Models\LibroMaster;
use App\Models\VentaDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class PrediccionDemandaController extends Controller
{
    private const SEMANAS_HISTORIAL = 16;
    private const ALPHA = 0.4; // suavizado exponencial: cuanto pesa la semana mas reciente

    public function index()
    {
        return Inertia::render('Reportes/Prediccion', [
            'categorias' => Categoria::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function buscar(Request $request)
    {
        $request->validate([
            'nivel' => 'required|in:libro,obra',
            'q'     => 'required|string|min:1',
        ]);

        $like = '%' . mb_strtolower($request->q) . '%';

        if ($request->nivel === 'obra') {
            $resultados = LibroMaster::whereRaw('LOWER(titulo) LIKE ?', [$like])
                ->orderBy('titulo')
                ->limit(15)
                ->get(['id', 'titulo'])
                ->map(fn($m) => ['id' => $m->id, 'label' => $m->titulo]);
        } else {
            $resultados = Libro::with('master:id,titulo')
                ->where(function ($q) use ($like) {
                    $q->whereHas('master', fn($q2) => $q2->whereRaw('LOWER(titulo) LIKE ?', [$like]))
                      ->orWhereRaw('LOWER(isbn) LIKE ?', [$like]);
                })
                ->orderBy('id')
                ->limit(15)
                ->get(['id', 'master_id', 'isbn', 'numero_tomo'])
                ->map(fn($l) => [
                    'id'    => $l->id,
                    'label' => ($l->master->titulo ?? 'Sin obra') . ($l->numero_tomo ? ' - Tomo ' . $l->numero_tomo : ''),
                ]);
        }

        return response()->json($resultados);
    }

    public function datos(Request $request)
    {
        $request->validate([
            'nivel' => 'required|in:libro,obra,categoria',
            'id'    => 'required|integer',
        ]);

        $desde = now()->subWeeks(self::SEMANAS_HISTORIAL)->startOfWeek();

        $query = VentaDetalle::query()
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->whereNotIn('ventas.estado', ['cancelado', 'pendiente_pago'])
            ->where('ventas.fecha', '>=', $desde);

        if ($request->nivel === 'libro') {
            $query->where('venta_detalles.libro_id', $request->id);
            $libro  = Libro::with('master:id,titulo')->find($request->id);
            $nombre = $libro ? ($libro->master->titulo ?? 'Libro') . ($libro->numero_tomo ? ' - Tomo ' . $libro->numero_tomo : '') : 'Libro';
        } elseif ($request->nivel === 'obra') {
            $query->join('libros', 'libros.id', '=', 'venta_detalles.libro_id')
                  ->where('libros.master_id', $request->id);
            $nombre = LibroMaster::find($request->id)?->titulo ?? 'Obra';
        } else {
            $query->join('libros', 'libros.id', '=', 'venta_detalles.libro_id')
                  ->join('libro_masters', 'libro_masters.id', '=', 'libros.master_id')
                  ->where('libro_masters.categoria_id', $request->id);
            $nombre = Categoria::find($request->id)?->nombre ?? 'Categoría';
        }

        $filas = $query->select('ventas.fecha', 'venta_detalles.cantidad')->get();

        // Bucketizar por semana en PHP (portable entre SQLite y Postgres, a
        // diferencia de usar funciones de fecha propias de un motor puntual).
        $semanas = [];
        for ($i = self::SEMANAS_HISTORIAL - 1; $i >= 0; $i--) {
            $inicio = now()->subWeeks($i)->startOfWeek();
            $semanas[$inicio->format('Y-m-d')] = 0;
        }

        foreach ($filas as $fila) {
            $inicio = Carbon::parse($fila->fecha)->startOfWeek()->format('Y-m-d');
            if (array_key_exists($inicio, $semanas)) {
                $semanas[$inicio] += (int) $fila->cantidad;
            }
        }

        $valores   = array_values($semanas);
        $etiquetas = array_map(fn($f) => Carbon::parse($f)->format('d/m'), array_keys($semanas));

        // Suavizado exponencial simple: S_t = alpha*X_t + (1-alpha)*S_(t-1)
        $suavizado = [];
        $anterior  = $valores[0] ?? 0;
        foreach ($valores as $v) {
            $anterior = self::ALPHA * $v + (1 - self::ALPHA) * $anterior;
            $suavizado[] = round($anterior, 2);
        }
        $pronostico = end($suavizado) ?: 0;

        $semanasConVentas = count(array_filter($valores, fn($v) => $v > 0));

        return response()->json([
            'nombre'                    => $nombre,
            'etiquetas'                 => $etiquetas,
            'valores'                   => $valores,
            'suavizado'                 => $suavizado,
            'pronostico_proxima_semana' => (int) round($pronostico),
            'historial_insuficiente'    => $semanasConVentas < 4,
            'semanas_con_ventas'        => $semanasConVentas,
        ]);
    }
}