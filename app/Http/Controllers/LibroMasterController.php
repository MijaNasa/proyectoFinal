<?php

namespace App\Http\Controllers;

use App\Models\LibroMaster;
use App\Http\Requests\StoreLibroMasterRequest;
use App\Http\Requests\UpdateLibroMasterRequest;
use Illuminate\Http\Request;

class LibroMasterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = LibroMaster::query()->with(['autor', 'categoria']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('titulo', 'like', '%' . $search . '%')
                  ->orWhere('titulo_original', 'like', '%' . $search . '%');
            });
        }

        $librosMaster = $query->latest()->paginate(10)->withQueryString();

        return inertia('LibroMasters/Index', [
            'librosMaster' => $librosMaster,
            'autores' => \App\Models\Autor::orderBy('apellido')->get(['id', 'nombre', 'apellido']),
            'categorias' => \App\Models\Categoria::orderBy('nombre')->get(['id', 'nombre']),
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLibroMasterRequest $request)
    {
        LibroMaster::create($request->validated());

        return redirect()->route('libro-masters.index')
            ->with('message', 'Libro Master creado con éxito');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLibroMasterRequest $request, LibroMaster $libroMaster)
    {
        $libroMaster->update($request->validated());

        return redirect()->route('libro-masters.index')
            ->with('message', 'Libro Master actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LibroMaster $libroMaster)
    {
        $libroMaster->delete();

        return redirect()->route('libro-masters.index')
            ->with('message', 'Libro Master eliminado con éxito');
    }
}
