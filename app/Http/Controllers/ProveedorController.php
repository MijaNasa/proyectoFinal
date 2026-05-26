<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use App\Models\Transaccion;
use App\Http\Requests\StoreProveedorRequest;
use App\Http\Requests\UpdateProveedorRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        $query = Proveedor::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre_empresa', 'like', '%' . $search . '%')
                  ->orWhere('nombre_contacto', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $proveedores = $query->latest()->paginate(10)->withQueryString();

        return inertia('Proveedores/Index', [
            'proveedores' => $proveedores,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(StoreProveedorRequest $request)
    {
        Proveedor::create($request->validated());

        return redirect()->route('proveedores.index')
            ->with('message', 'Proveedor creado con éxito');
    }

    public function update(UpdateProveedorRequest $request, Proveedor $proveedor)
    {
        $proveedor->update($request->validated());

        return redirect()->route('proveedores.index')
            ->with('message', 'Proveedor actualizado con éxito');
    }

    public function registrarPago(Request $request, Proveedor $proveedor)
    {
        $request->validate([
            'monto'       => 'required|numeric|min:0.01',
            'metodo_pago' => 'required|in:Efectivo,Transferencia,Tarjeta,Débito',
            'descripcion' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request, $proveedor) {
            $proveedor->decrement('deuda_actual', $request->monto);

            Transaccion::create([
                'tipo'                 => 'egreso',
                'monto'                => $request->monto,
                'metodo_pago'          => $request->metodo_pago,
                'fecha'                => now(),
                'sucursal_id'          => auth()->user()->empleado?->sucursal_id,
                'transaccionable_id'   => $proveedor->id,
                'transaccionable_type' => Proveedor::class,
                'descripcion'          => $request->descripcion ?: 'Pago a proveedor',
                'user_id'              => auth()->id(),
            ]);
        });

        return redirect()->route('proveedores.index')->with('message', 'Pago registrado con éxito');
    }

    public function destroy(Proveedor $proveedor)
    {
        $tieneSeries = $proveedor->series()->exists();

        if ($tieneSeries) {
            return redirect()->back()
                ->with('error', 'No se puede eliminar el proveedor porque tiene series asociadas.');
        }

        $proveedor->delete();

        return redirect()->route('proveedores.index')
            ->with('message', 'Proveedor eliminado con éxito');
    }
}
