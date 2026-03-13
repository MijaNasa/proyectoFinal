<?php

namespace App\Http\Controllers;

use App\Models\Sucursal;
use App\Http\Requests\StoreSucursalRequest;
use App\Http\Requests\UpdateSucursalRequest;
use Illuminate\Http\Request;

class SucursalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Sucursal::query()->with('ciudad.provincia.pais');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'like', '%' . $search . '%')
                  ->orWhere('codigo', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $sucursales = $query->latest()->paginate(10)->withQueryString();

        return inertia('Sucursales/Index', [
            'sucursales' => $sucursales,
            'ciudades' => \App\Models\Ciudad::with('provincia.pais')->orderBy('nombre')->get(),
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSucursalRequest $request)
    {
        Sucursal::create($request->validated());

        return redirect()->route('sucursales.index')
            ->with('message', 'Sucursal creada con éxito');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSucursalRequest $request, Sucursal $sucursal)
    {
        $sucursal->update($request->validated());

        return redirect()->route('sucursales.index')
            ->with('message', 'Sucursal actualizada con éxito');
    }

    public function destroy(Sucursal $sucursal)
    {
        // Verificar si hay stock activo en esta sucursal
        $tieneStock = \App\Models\Stock::where('sucursal_id', $sucursal->id)
            ->where('cantidad_disponible', '>', 0)
            ->exists();

        if ($tieneStock) {
            return redirect()->back()->with('error', 'No se puede eliminar la sucursal porque todavía tiene stock disponible.');
        }

        $sucursal->delete();

        return redirect()->route('sucursales.index')
            ->with('message', 'Sucursal eliminada con éxito');
    }
}
