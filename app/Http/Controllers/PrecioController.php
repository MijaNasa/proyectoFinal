<?php

namespace App\Http\Controllers;

use App\Models\Libro;
use App\Models\PrecioLibro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PrecioController extends Controller
{
    public function bulkUpdate(Request $request)
    {
        // 1. Validamos que lleguen los datos obligatorios
        $request->validate([
            'criterio'     => 'required|in:serie,editorial_formato,libro_individual',
            'nuevo_precio' => 'required|numeric|min:0',
            'libro_id'     => 'nullable|exists:libros,id'
        ]);

        $query = \App\Models\Libro::query();

        // 2. Filtramos según lo que eligió el usuario
        if ($request->criterio === 'serie') {
            $query->whereHas('serie', function ($q) use ($request) {
                $q->where('nombre', $request->serie);
            });
        } elseif ($request->criterio === 'libro_individual') {
            $query->where('id', $request->libro_id);
        } else {
            // Editorial y formato ahora viven en LibroMaster (la obra), no en Libro (la edición).
            $query->whereHas('master', function ($q) use ($request) {
                $q->where('formato', $request->formato)
                  ->whereHas('editorial', function ($eq) use ($request) {
                      $eq->where('nombre', $request->editorial);
                  });
            });
        }

        $libros = $query->get();

        // 3. Aplicamos el aumento a todos los libros encontrados
        foreach ($libros as $libro) {
            // Capturamos el precio viejo para no perder el dato del costo original
            $precioViejo = $libro->precios()->where('activo', true)->first();
            $costoActual = $precioViejo ? $precioViejo->precio_compra : 0;

            // Desactivamos el historial viejo
            $libro->precios()->update(['activo' => false]);

            // Creamos el nuevo precio (usando la misma estructura de tu LibroController)
            $libro->precios()->create([
                'precio_compra' => $costoActual,
                'precio_venta'  => $request->nuevo_precio,
                'motivo'        => $request->motivo ?? 'Aumento masivo',
                'fecha_desde'   => now(),
                'activo'        => true,
            ]);
        }

        return redirect()->back();
    }

public function index(Request $request): \Inertia\Response
    {
        $filtro = $request->get('filtro', 'todos');
        $search = $request->get('search', '');

        $query = Libro::with([
            'master:id,titulo,autor_id,editorial_id,formato',
            'master.autor:id,nombre,apellido',
            'master.editorial:id,nombre',
            'serie:id,nombre', // <-- ¡AGREGADO! Para que funcione el filtro masivo por Serie
            'precios' => fn($q) => $q->orderByDesc('fecha_desde')->limit(5),
        ])
        ->select(
            'libros.id',
            'libros.isbn',
            'libros.master_id',
            'libros.serie_id', // <-- ¡AGREGADO! Necesario para la relación de arriba
            'libros.año_edicion',
            'libros.activo'
        );

        if ($search) {
            $query->whereHas('master', fn($q) => $q
                ->where('titulo', 'like', "%{$search}%")
                ->orWhereHas('autor', fn($sq) => $sq
                    ->where('nombre', 'like', "%{$search}%")
                    ->orWhere('apellido', 'like', "%{$search}%")
                )
            )->orWhere('libros.isbn', 'like', "%{$search}%");
        }

        if ($filtro === 'sin_precio') {
            $query->whereDoesntHave('precios', fn($q) => $q->where('activo', true));
        }

        $libros = $query->orderBy('libros.id', 'desc')->paginate(30)->withQueryString();

        // Agregar precio actual calculado
        $libros->getCollection()->transform(function ($libro) {
            $libro->precio_actual = $libro->precios->firstWhere('activo', true);
            return $libro;
        });

        $stats = [
            'total'      => Libro::count(),
            'con_precio' => Libro::whereHas('precios', fn($q) => $q->where('activo', true))->count(),
            'sin_precio' => Libro::whereDoesntHave('precios', fn($q) => $q->where('activo', true))->count(),
        ];

        $opcionesMasivas = [
            // El formato ahora es un atributo de LibroMaster (la obra), no de Libro (la edición).
            'formatos' => \App\Models\LibroMaster::whereNotNull('formato')->where('formato', '!=', '')->distinct()->pluck('formato'),
            'series' => \App\Models\Serie::orderBy('nombre')->pluck('nombre'),
            'editoriales' => \App\Models\Editorial::orderBy('nombre')->pluck('nombre'),
            'libros' => Libro::with('master:id,titulo')->select('id', 'master_id', 'isbn')->get()->map(function($l) {
                return [
                    'id' => $l->id,
                    'titulo' => $l->master->titulo . ($l->isbn ? ' (ISBN: ' . $l->isbn . ')' : '')
                ];
            })->sortBy('titulo')->values()
        ];

        return inertia('Precios/Index', [
            'libros'  => $libros,
            'stats'   => $stats,
            'opcionesMasivas' => $opcionesMasivas,
            'filters' => compact('filtro', 'search'),
        ]);
    }

    public function store(Request $request, Libro $libro): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'precio_venta'  => 'required|numeric|min:0',
            'precio_compra' => 'nullable|numeric|min:0',
            'motivo'        => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request, $libro) {
            // Lock the libro row to serialize concurrent price updates for the same book
            \App\Models\Libro::lockForUpdate()->find($libro->id);

            // Cerrar precio anterior
            $libro->precios()->where('activo', true)->update([
                'activo'      => false,
                'fecha_hasta' => now(),
            ]);

            // Crear nuevo precio
            $libro->precios()->create([
                'precio_venta'  => $request->precio_venta,
                'precio_compra' => $request->precio_compra,
                'motivo'        => $request->motivo,
                'fecha_desde'   => now(),
                'activo'        => true,
            ]);
        });

        return back()->with('message', 'Precio actualizado correctamente');
    }

    public function historial(Libro $libro): \Illuminate\Http\JsonResponse
    {
        $historial = $libro->precios()
            ->orderByDesc('fecha_desde')
            ->get(['id', 'precio_venta', 'precio_compra', 'motivo', 'fecha_desde', 'fecha_hasta', 'activo']);

        return response()->json($historial);
    }
}
