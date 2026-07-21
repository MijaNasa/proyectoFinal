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

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $sucursales = $query->latest()->paginate(10)->withQueryString();

        return inertia('Sucursales/Index', [
            'sucursales' => $sucursales,
            'ciudades' => \App\Models\Ciudad::with('provincia.pais')
                ->whereHas('provincia', fn($q) => $q->where('nombre', 'like', '%Santa Fe%'))
                ->orderBy('nombre')->get(),
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSucursalRequest $request)
    {
        $data = $request->validated();
        
        $provincia = \App\Models\Provincia::first();
        $ciudad = \App\Models\Ciudad::firstOrCreate([
            'nombre' => $data['ciudad_nombre'],
            'provincia_id' => $provincia->id
        ]);
        $data['ciudad_id'] = $ciudad->id;
        unset($data['ciudad_nombre']);

        if (!empty($data['es_principal'])) {
            Sucursal::where('id', '!=', 0)->update(['es_principal' => false]);
        }

        Sucursal::create($data);

        return redirect()->route('sucursales.index')
            ->with('message', 'Sucursal creada con éxito');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSucursalRequest $request, Sucursal $sucursal)
    {
        $data = $request->validated();
        
        $provincia = \App\Models\Provincia::first();
        $ciudad = \App\Models\Ciudad::firstOrCreate([
            'nombre' => $data['ciudad_nombre'],
            'provincia_id' => $provincia->id
        ]);
        $data['ciudad_id'] = $ciudad->id;
        unset($data['ciudad_nombre']);

        if (!empty($data['es_principal'])) {
            Sucursal::where('id', '!=', $sucursal->id)->update(['es_principal' => false]);
        }

        $sucursal->update($data);

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

        // Verificar si hay ventas sin terminar (estado_envio ya no existe, se unifico en 'estado')
        $tieneEnviosPendientes = \App\Models\Venta::where('sucursal_id', $sucursal->id)
            ->whereNotIn('estado', ['finalizado', 'cancelado'])
            ->exists();

        if ($tieneEnviosPendientes) {
            return redirect()->back()->with('error', 'No se puede eliminar la sucursal porque tiene ventas con envíos pendientes.');
        }

        $sucursal->delete();

        return redirect()->route('sucursales.index')
            ->with('message', 'Sucursal eliminada con éxito');
    }
}
