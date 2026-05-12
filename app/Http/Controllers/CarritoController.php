<?php

namespace App\Http\Controllers;

use App\Models\Libro;
use Illuminate\Http\Request;
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
            'cantidad' => 'required|integer|min:1|max:5',
        ]);

        $libro = Libro::with(['master', 'precioActual', 'stocks'])->findOrFail($request->libro_id);

        $stockTotal = $libro->stocks->sum('cantidad_disponible');
        if ($stockTotal === 0) {
            return back()->with('error', 'Este libro no tiene stock disponible.');
        }

        $carrito = session(self::SESSION_KEY, []);
        $id = $request->libro_id;

        $limite = min(5, $stockTotal);
        $cantidadActual = $carrito[$id]['cantidad'] ?? 0;

        if ($cantidadActual >= $limite) {
            $motivo = $stockTotal <= 5 ? 'No hay más stock disponible.' : 'Llegaste al límite de 5 unidades por compra.';
            return back()->with('warning', $motivo);
        }

        $nuevaCantidad = min($cantidadActual + $request->cantidad, $limite);

        $carrito[$id] = [
            'libro_id'    => $libro->id,
            'cantidad'    => $nuevaCantidad,
            'precio'      => $libro->precioActual?->precio_venta ?? 0,
            'titulo'      => $libro->master->titulo,
            'portada_url' => $libro->master->portada_url,
            'isbn'        => $libro->isbn,
            'editorial'   => $libro->editorial->nombre ?? '',
        ];

        session([self::SESSION_KEY => $carrito]);

        return back()->with('success', 'Libro agregado al carrito.');
    }

    public function actualizar(Request $request, $libroId)
    {
        $request->validate(['cantidad' => 'required|integer|min:1|max:99']);

        $carrito = session(self::SESSION_KEY, []);

        if (!isset($carrito[$libroId])) {
            return back();
        }

        $libro = Libro::with('stocks')->findOrFail($libroId);
        $stockTotal = $libro->stocks->sum('cantidad_disponible');

        $carrito[$libroId]['cantidad'] = min($request->cantidad, $stockTotal);
        session([self::SESSION_KEY => $carrito]);

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

        $total = collect($carrito)->sum(fn($item) => $item['precio'] * $item['cantidad']);

        return [
            'items' => array_values($carrito),
            'total' => $total,
            'count' => array_sum(array_column($carrito, 'cantidad')),
        ];
    }

    public static function getCount(): int
    {
        $carrito = session(self::SESSION_KEY, []);
        return array_sum(array_column($carrito, 'cantidad'));
    }
}
