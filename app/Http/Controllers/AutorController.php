<?php

namespace App\Http\Controllers;

use App\Models\Autor;
use App\Http\Requests\StoreAutorRequest;
use App\Http\Requests\UpdateAutorRequest;
use Illuminate\Http\Request;

class AutorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Autor::query()->with('pais');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'like', '%' . $search . '%')
                  ->orWhere('apellido', 'like', '%' . $search . '%');
            });
        }

        $autores = $query->latest()->paginate(10)->withQueryString();

        return inertia('Autores/Index', [
            'autores' => $autores,
            'paises' => \App\Models\Pais::orderBy('nombre')->get(['id', 'nombre']),
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAutorRequest $request)
    {
        Autor::create($request->validated());

        return redirect()->route('autores.index')
            ->with('message', 'Autor creado con éxito');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAutorRequest $request, Autor $autor)
    {
        $autor->update($request->validated());

        return redirect()->route('autores.index')
            ->with('message', 'Autor actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Autor $autor)
    {
        $autor->delete();

        return redirect()->route('autores.index')
            ->with('message', 'Autor eliminado con éxito');
    }
}
