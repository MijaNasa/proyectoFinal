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
        $estado = $request->get('estado', 'activas');
        $query = Sucursal::query()->with('ciudad.provincia.pais');

        if ($request->filled('search')) {
            $like = '%' . mb_strtolower($request->search) . '%';
            $query->where(function($q) use ($like) {
                $q->whereRaw('LOWER(nombre) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
            });
        }

        if ($estado === 'inactivas') {
            $query->where('activo', false);
        } elseif ($estado === 'todas') {
            // todas las sucursales
        } else {
            $query->where('activo', true);
        }

        $sucursales = $query->latest()->paginate(10)->withQueryString();

        $stats = [
            'activas'   => Sucursal::where('activo', true)->count(),
            'inactivas' => Sucursal::where('activo', false)->count(),
            'todas'     => Sucursal::count(),
        ];

        return inertia('Sucursales/Index', [
            'sucursales' => $sucursales,
            'ciudades' => \App\Models\Ciudad::with('provincia.pais')
                ->whereHas('provincia', fn($q) => $q->where('nombre', 'like', '%Santa Fe%'))
                ->orderBy('nombre')->get(),
            'stats'   => $stats,
            'filters' => array_merge($request->only(['search', 'estado']), ['estado' => $estado])
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSucursalRequest $request)
    {
        if (!$request->user() || !$request->user()->esAdmin()) {
            abort(403, 'No tenés permisos para crear sucursales.');
        }

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
        if (!$request->user() || !$request->user()->esAdmin()) {
            abort(403, 'No tenés permisos para editar sucursales.');
        }

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
        if (!auth()->user() || !auth()->user()->esAdmin()) {
            abort(403, 'No tenés permisos para eliminar o desactivar sucursales.');
        }

        if ($sucursal->es_principal) {
            return redirect()->back()->with('error_modal', 'No se puede desactivar la sucursal principal. Asigna otra sucursal como principal antes.');
        }

        // Verificar si hay ventas sin terminar
        $tieneEnviosPendientes = \App\Models\Venta::where('sucursal_id', $sucursal->id)
            ->whereNotIn('estado', ['finalizado', 'cancelado', 'enviado'])
            ->exists();

        if ($tieneEnviosPendientes) {
            return redirect()->back()->with('error_modal', 'No se puede desactivar la sucursal porque tiene ventas con entregas o retiros pendientes.');
        }

        // Verificar si tiene stock disponible o reservado
        $totalStock = (int) $sucursal->stocks()
            ->where(function ($q) {
                $q->where('cantidad_disponible', '>', 0)
                  ->orWhere('cantidad_reservada', '>', 0);
            })
            ->sum(\DB::raw('cantidad_disponible + cantidad_reservada'));

        if ($totalStock > 0) {
            return redirect()->back()->with('error_modal', "No se puede desactivar la sucursal porque todavía posee {$totalStock} unidad(es) de stock en inventario. Primero debe vaciar o transferir el stock a otra sucursal.");
        }

        // Desactivación lógica para preservar trazabilidad de ventas históricas y stock
        $sucursal->update(['activo' => false]);

        return redirect()->route('sucursales.index')
            ->with('swal_success', 'Sucursal desactivada con éxito. Su historial de ventas y trazabilidad se conservan intactos.');
    }

    public function toggleActivo(Request $request, Sucursal $sucursal)
    {
        if (!$request->user() || !$request->user()->esAdmin()) {
            abort(403, 'No tenés permisos para cambiar el estado de sucursales.');
        }

        if ($sucursal->activo && $sucursal->es_principal) {
            return redirect()->back()->with('error_modal', 'No se puede desactivar la sucursal principal. Asigna otra sucursal como principal primero.');
        }

        $nuevoEstado = !$sucursal->activo;

        if (!$nuevoEstado) {
            $tieneEnviosPendientes = \App\Models\Venta::where('sucursal_id', $sucursal->id)
                ->whereNotIn('estado', ['finalizado', 'cancelado', 'enviado'])
                ->exists();

            if ($tieneEnviosPendientes) {
                return redirect()->back()->with('error_modal', 'No se puede desactivar la sucursal porque tiene ventas con entregas o retiros pendientes.');
            }

            $totalStock = (int) $sucursal->stocks()
                ->where(function ($q) {
                    $q->where('cantidad_disponible', '>', 0)
                      ->orWhere('cantidad_reservada', '>', 0);
                })
                ->sum(\DB::raw('cantidad_disponible + cantidad_reservada'));

            if ($totalStock > 0) {
                return redirect()->back()->with('error_modal', "No se puede desactivar la sucursal porque todavía posee {$totalStock} unidad(es) de stock en inventario. Primero debe vaciar o transferir el stock a otra sucursal.");
            }
        }

        $sucursal->update(['activo' => $nuevoEstado]);

        $mensaje = $nuevoEstado ? 'Sucursal reactivada con éxito.' : 'Sucursal desactivada con éxito.';

        return redirect()->back()->with('swal_success', $mensaje);
    }
}
