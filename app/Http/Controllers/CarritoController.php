<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\VentaDetalle;
use App\Models\Libro;
use App\Models\Stock;
use App\Models\Suscripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CarritoController extends Controller
{
    private const SESSION_KEY = 'carrito';

    public function index()
    {
        return Inertia::render('Carrito/Index', [
            'items' => $this->getItems(),
        ]);
    }

    public function agregar(Request $request)
    {
        $request->validate([
            'libro_id' => 'required|exists:libros,id',
            'cantidad' => 'required|integer|min:1',
        ]);

        $libro = Libro::with(['master.proveedor', 'precioActual', 'stocks'])->findOrFail($request->libro_id);

        $stockTotal = $libro->stocks->sum('cantidad_disponible');
        if (!$libro->permite_preventa && $stockTotal === 0) {
            return back()->with('error', 'Este libro no tiene stock disponible.');
        }

        $carrito = session(self::SESSION_KEY, []);
        $id = $request->libro_id;

        $limite = $libro->permite_preventa ? 5 : ($stockTotal > 0 ? min(5, $stockTotal) : 5);
        $cantidadActual = $carrito[$id]['cantidad'] ?? 0;

        if ($cantidadActual >= $limite) {
            return back()->with('warning', 'Alcanzaste el límite de unidades de este producto para esta compra.');
        }

        $nuevaCantidad = min($cantidadActual + $request->cantidad, $limite);

        $tomoLabel = $libro->numero_tomo ? (preg_match('/^tomo\b/i', trim($libro->numero_tomo)) ? ' - ' . trim($libro->numero_tomo) : ' - Tomo ' . trim($libro->numero_tomo)) : '';
        $carrito[$id] = [
            'libro_id'         => $libro->id,
            'master_id'        => $libro->master_id,
            'numero_tomo'      => $libro->numero_tomo,
            'cantidad'         => $nuevaCantidad,
            'precio'           => $libro->permite_preventa ? ($libro->precioActual?->precio_venta * 0.90) : ($libro->precioActual?->precio_venta ?? 0),
            'precio_original'  => $libro->precioActual?->precio_venta ?? 0,
            'permite_preventa' => $libro->permite_preventa,
            'titulo'           => $libro->master->titulo . $tomoLabel,
            'portada_url'      => $libro->master->portada_url,
            'isbn'             => $libro->isbn,
            'proveedor'        => $libro->master->proveedor->nombre_empresa ?? '',
            'stock_total'      => $stockTotal,
        ];

        session([self::SESSION_KEY => $carrito]);

        return back()->with('success', 'Libro agregado al carrito.');
    }

    public function actualizar(Request $request, $libroId)
    {
        $request->validate(['cantidad' => 'required|integer|min:1']);

        $carrito = session(self::SESSION_KEY, []);

        if (!isset($carrito[$libroId])) {
            return back();
        }

        $libro = Libro::findOrFail($libroId);
        $stockTotal = Stock::where('libro_id', $libro->id)->sum('cantidad_disponible');

        $limite = $libro->permite_preventa ? 5 : ($stockTotal > 0 ? min(5, $stockTotal) : 5);
        $carrito[$libroId]['cantidad'] = min($request->cantidad, $limite);
        $carrito[$libroId]['stock_total'] = $stockTotal;
        $carrito[$libroId]['master_id'] = $libro->master_id;
        $carrito[$libroId]['numero_tomo'] = $libro->numero_tomo;
        session([self::SESSION_KEY => $carrito]);

        if ($request->cantidad > $limite) {
            return back()->with('warning', 'Alcanzaste el límite de unidades de este producto para esta compra.');
        }

        return back();
    }

    public function quitar($libroId)
    {
        $carrito = session(self::SESSION_KEY, []);
        unset($carrito[$libroId]);
        session([self::SESSION_KEY => $carrito]);

        return back();
    }

    public function vaciar()
    {
        session()->forget(self::SESSION_KEY);
        return back();
    }

    private function getItems(): array
    {
        $carrito = session(self::SESSION_KEY, []);
        $items = array_values($carrito);

        $subtotal = (float) collect($items)->sum(fn($item) => ($item['precio'] ?? 0) * ($item['cantidad'] ?? 0));

        $descuentoSuscripcion = 0;
        $user = Auth::user();
        if ($user) {
            $cliente = Cliente::where('user_id', $user->id)->first();
            if ($cliente) {
                $suscripciones = Suscripcion::where('cliente_id', $cliente->id)
                    ->where('estado', 'activa')
                    ->get()
                    ->keyBy('libro_master_id');

                if ($suscripciones->isNotEmpty()) {
                    $libroIds = collect($items)->pluck('libro_id')->filter()->all();
                    $librosModels = Libro::whereIn('id', $libroIds)->get()->keyBy('id');

                    // Libros ya comprados por este cliente en compras previas finalizadas/activas
                    $compradosIds = VentaDetalle::whereHas('venta', function ($q) use ($cliente) {
                        $q->where('cliente_id', $cliente->id)
                          ->where('estado', '!=', 'cancelado');
                    })->whereIn('libro_id', $libroIds)->pluck('libro_id')->toArray();

                    foreach ($items as &$it) {
                        $lModel = $librosModels->get($it['libro_id']);
                        $mId = $it['master_id'] ?? $lModel?->master_id;
                        $it['master_id'] = $mId;

                        $rawTomo = (string) ($it['numero_tomo'] ?? $lModel?->numero_tomo ?? '1');
                        $numTomo = (int) preg_replace('/\D/', '', $rawTomo) ?: 1;

                        $sub = $suscripciones->get($mId);
                        $aplica = false;

                        if ($sub && !in_array($it['libro_id'], $compradosIds)) {
                            $tomoInicio = $sub->tomo_inicio ?? 1;
                            if ($numTomo >= $tomoInicio) {
                                $aplica = true;
                            }
                        }

                        if ($aplica) {
                            $descItem = round(($it['precio'] ?? 0) * 0.05, 2);
                            $descuentoSuscripcion += $descItem;
                            $it['tiene_descuento_suscripcion'] = true;
                            $it['descuento_suscripcion_unitario'] = $descItem;
                        } else {
                            $it['tiene_descuento_suscripcion'] = false;
                        }
                    }
                    unset($it);
                }
            }
        }

        $total = max(0, $subtotal - $descuentoSuscripcion);

        return [
            'items'                 => $items,
            'subtotal'              => $subtotal,
            'descuento_suscripcion' => $descuentoSuscripcion,
            'total'                 => $total,
            'count'                 => array_sum(array_column($items, 'cantidad')),
        ];
    }

    public static function getCount(): int
    {
        $carrito = session(self::SESSION_KEY, []);
        return array_sum(array_column($carrito, 'cantidad'));
    }

    public static function getTotal(): float
    {
        $carrito = session(self::SESSION_KEY, []);
        $subtotal = (float) collect($carrito)->sum(fn($item) => ($item['precio'] ?? 0) * ($item['cantidad'] ?? 0));

        $user = Auth::user();
        if ($user) {
            $cliente = Cliente::where('user_id', $user->id)->first();
            if ($cliente) {
                $suscripciones = Suscripcion::where('cliente_id', $cliente->id)
                    ->where('estado', 'activa')
                    ->get()
                    ->keyBy('libro_master_id');

                if ($suscripciones->isNotEmpty()) {
                    $libroIds = collect($carrito)->pluck('libro_id')->filter()->all();
                    $librosModels = Libro::whereIn('id', $libroIds)->get()->keyBy('id');

                    $compradosIds = VentaDetalle::whereHas('venta', function ($q) use ($cliente) {
                        $q->where('cliente_id', $cliente->id)
                          ->where('estado', '!=', 'cancelado');
                    })->whereIn('libro_id', $libroIds)->pluck('libro_id')->toArray();

                    $descuentoSuscripcion = 0;
                    foreach ($carrito as $it) {
                        $lModel = $librosModels->get($it['libro_id']);
                        $mId = $it['master_id'] ?? $lModel?->master_id;
                        $rawTomo = (string) ($it['numero_tomo'] ?? $lModel?->numero_tomo ?? '1');
                        $numTomo = (int) preg_replace('/\D/', '', $rawTomo) ?: 1;

                        $sub = $suscripciones->get($mId);
                        if ($sub && !in_array($it['libro_id'], $compradosIds)) {
                            $tomoInicio = $sub->tomo_inicio ?? 1;
                            if ($numTomo >= $tomoInicio) {
                                $descuentoSuscripcion += round(($it['precio'] ?? 0) * 0.05, 2);
                            }
                        }
                    }
                    return max(0, (float) ($subtotal - $descuentoSuscripcion));
                }
            }
        }

        return $subtotal;
    }
}
