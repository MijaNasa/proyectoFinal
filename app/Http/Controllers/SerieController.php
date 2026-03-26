<?php

namespace App\Http\Controllers;

use App\Models\Serie;
use App\Http\Requests\StoreSerieRequest;
use App\Http\Requests\UpdateSerieRequest;
use Illuminate\Http\Request;

class SerieController extends Controller
{
    public function index(Request $request)
    {
        $query = Serie::query()->with('proveedor');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('nombre', 'like', '%' . $search . '%');
        }

        $series = $query->latest()->paginate(10)->withQueryString();

        return inertia('Series/Index', [
            'series' => $series,
            'proveedores' => \App\Models\Proveedor::where('activo', true)->orderBy('nombre_empresa')->get(['id', 'nombre_empresa']),
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(StoreSerieRequest $request)
    {
        Serie::create($request->validated());

        return redirect()->route('series.index')
            ->with('message', 'Serie creada con éxito');
    }

    public function update(UpdateSerieRequest $request, Serie $serie)
    {
        $serie->update($request->validated());

        return redirect()->route('series.index')
            ->with('message', 'Serie actualizada con éxito');
    }

    public function destroy(Serie $serie)
    {
        $tieneLibros = $serie->libros()->exists();

        if ($tieneLibros) {
            return redirect()->back()
                ->with('error', 'No se puede eliminar la serie porque tiene libros asociados.');
        }

        $serie->delete();

        return redirect()->route('series.index')
            ->with('message', 'Serie eliminada con éxito');
    }
}
