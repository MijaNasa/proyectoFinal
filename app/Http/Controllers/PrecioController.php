<?php

namespace App\Http\Controllers;

use App\Models\Libro;
use App\Models\PrecioLibro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrecioController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $filtro = $request->get('filtro', 'todos');
        $search = $request->get('search', '');

        $query = Libro::with([
            'master:id,titulo,autor_id',
            'master.autor:id,nombre,apellido',
            'editorial:id,nombre',
            'precios' => fn($q) => $q->orderByDesc('fecha_desde')->limit(5),
        ])
        ->select('libros.id', 'libros.isbn', 'libros.master_id', 'libros.editorial_id', 'libros.año_edicion', 'libros.activo');

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

        return inertia('Precios/Index', [
            'libros'  => $libros,
            'stats'   => $stats,
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
