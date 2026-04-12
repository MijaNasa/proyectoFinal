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
        $librosMaster = LibroMaster::query()
            ->with(['autor', 'categoria'])
            ->latest()
            ->get();

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
        $data = $request->validated();

        if ($request->hasFile('portada')) {
            $data['portada'] = $request->file('portada')->store('portadas', 'public');
        }

        LibroMaster::create($data);

        return redirect()->route('libro-masters.index')
            ->with('message', 'Libro Master creado con éxito');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLibroMasterRequest $request, LibroMaster $libroMaster)
    {
        $data = $request->validated();

        if ($request->hasFile('portada')) {
            // Eliminar imagen anterior si existe
            if ($libroMaster->portada && \Illuminate\Support\Facades\Storage::disk('public')->exists($libroMaster->portada)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($libroMaster->portada);
            }
            $data['portada'] = $request->file('portada')->store('portadas', 'public');
        }

        $libroMaster->update($data);

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
