<?php

namespace App\Http\Controllers;

use App\Models\Editorial;
use App\Http\Requests\StoreEditorialRequest;
use App\Http\Requests\UpdateEditorialRequest;
use Illuminate\Http\Request;

class EditorialController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Editorial::query()->with('pais');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $editoriales = $query->latest()->paginate(10)->withQueryString();

        return inertia('Editoriales/Index', [
            'editoriales' => $editoriales,
            'paises' => \App\Models\Pais::orderBy('nombre')->get(['id', 'nombre']),
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEditorialRequest $request)
    {
        Editorial::create($request->validated());

        return redirect()->route('editoriales.index')
            ->with('message', 'Editorial creada con éxito');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEditorialRequest $request, Editorial $editorial)
    {
        $editorial->update($request->validated());

        return redirect()->route('editoriales.index')
            ->with('message', 'Editorial actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Editorial $editorial)
    {
        $editorial->delete();

        return redirect()->route('editoriales.index')
            ->with('message', 'Editorial eliminada con éxito');
    }
}
