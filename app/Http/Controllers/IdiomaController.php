<?php

namespace App\Http\Controllers;

use App\Models\Idioma;
use App\Http\Requests\StoreIdiomaRequest;
use App\Http\Requests\UpdateIdiomaRequest;
use Illuminate\Http\Request;

class IdiomaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Idioma::query();

        if ($request->has('search')) {
            $query->where('nombre', 'like', '%' . $request->search . '%');
        }

        $idiomas = $query->latest()->paginate(10)->withQueryString();

        return inertia('Idiomas/Index', [
            'idiomas' => $idiomas,
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreIdiomaRequest $request)
    {
        Idioma::create($request->validated());

        return redirect()->route('idiomas.index')
            ->with('message', 'Idioma creado con éxito');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateIdiomaRequest $request, Idioma $idioma)
    {
        $idioma->update($request->validated());

        return redirect()->route('idiomas.index')
            ->with('message', 'Idioma actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Idioma $idioma)
    {
        $idioma->delete();

        return redirect()->route('idiomas.index')
            ->with('message', 'Idioma eliminado con éxito');
    }
}
