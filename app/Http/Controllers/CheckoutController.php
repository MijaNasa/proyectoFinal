<?php

namespace App\Http\Controllers;

use App\Models\PrecioLibro;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;

class CheckoutController extends Controller
{
    public function __construct()
    {
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
    }

    public function index()
    {
        if (!Auth::check()) {
            return redirect()->guest(route('login'))
                ->with('warning', 'Iniciá sesión para continuar con tu compra.');
        }

        $carrito = session('carrito', []);

        if (empty($carrito)) {
            return redirect()->route('carrito.index')
                ->with('error', 'Tu carrito está vacío.');
        }

        $total = collect($carrito)->sum(fn($i) => $i['precio'] * $i['cantidad']);
        
        $cliente = \App\Models\Cliente::where('user_id', Auth::id())->first();
        $sucursales = \App\Models\Sucursal::where('activo', true)->get(['id', 'nombre']);

        $libroIds = collect($carrito)->pluck('libro_id');
        $stocks = \App\Models\Stock::whereIn('libro_id', $libroIds)
            ->whereIn('sucursal_id', $sucursales->pluck('id'))
            ->get();
            
        $sucursales = $sucursales->map(function($sucursal) use ($stocks, $carrito) {
            $tieneStock = true;
            foreach ($carrito as $item) {
                $stockItem = $stocks->where('sucursal_id', $sucursal->id)
                                    ->where('libro_id', $item['libro_id'])
                                    ->first();
                if (!$stockItem || $stockItem->cantidad_disponible < $item['cantidad']) {
                    $tieneStock = false;
                    break;
                }
            }
            $sucursal->tiene_stock = $tieneStock;
            return $sucursal;
        });

        return Inertia::render('Checkout/Index', [
            'items'        => array_values($carrito),
            'total'        => $total,
            'saldo_actual' => $cliente ? $cliente->saldo_actual : 0,
            'sucursales'   => $sucursales,
        ]);
    }

    public function store(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $request->validate([
            'tipo_envio'            => 'required|in:retiro,domicilio,acumulacion',
            'sucursal_id'           => 'required_if:tipo_envio,retiro|required_if:tipo_envio,acumulacion|nullable|exists:sucursales,id',
            'direccion_envio'       => 'required_if:tipo_envio,domicilio|nullable|string|max:255',
            'medio_pago'            => 'required|in:Efectivo,Tarjeta,Transferencia,Cuenta Corriente',
            'metodo_pago_excedente' => 'nullable|string|in:Efectivo,Tarjeta,Transferencia',
        ], [
            'tipo_envio.required'         => 'Seleccioná el tipo de entrega.',
            'direccion_envio.required_if' => 'Ingresá la dirección de entrega.',
            'sucursal_id.required_if'     => 'Seleccioná la sucursal.',
            'medio_pago.required'         => 'Seleccioná el método de pago.',
        ]);

        if (in_array($request->tipo_envio, ['domicilio', 'acumulacion'])) {
            if ($request->medio_pago === 'Efectivo') {
                return back()->withErrors(['medio_pago' => 'El pago en Efectivo solo está disponible para retiro en sucursal.']);
            }
            if ($request->medio_pago === 'Cuenta Corriente' && $request->metodo_pago_excedente === 'Efectivo') {
                return back()->withErrors(['metodo_pago_excedente' => 'El pago del excedente en Efectivo solo está disponible para retiro en sucursal.']);
            }
        }

        $carrito = session('carrito', []);

        if (empty($carrito)) {
            return redirect()->route('carrito.index')
                ->with('error', 'Tu carrito está vacío.');
        }

        // Determinar sucursal
        $sucursal_id = $request->tipo_envio === 'domicilio'
            ? (Sucursal::where('es_deposito_central', true)->where('activo', true)->value('id') ?? Sucursal::where('activo', true)->value('id'))
            : $request->sucursal_id;

        if (!$sucursal_id) {
            return redirect()->route('carrito.index')
                ->with('error', 'No hay sucursales disponibles para procesar tu compra. Por favor contactanos.');
        }

        $libroIds = collect($carrito)->pluck('libro_id');

        $precios = PrecioLibro::whereIn('libro_id', $libroIds)
            ->where('activo', true)
            ->where(fn($q) => $q->whereNull('fecha_hasta')->orWhere('fecha_hasta', '>', now()))
            ->orderByDesc('fecha_desde')
            ->get()
            ->unique('libro_id')
            ->keyBy('libro_id');

        foreach ($libroIds as $libroId) {
            if (!isset($precios[$libroId])) {
                return redirect()->route('carrito.index')
                    ->with('error', 'Uno o más libros del carrito no tienen precio activo. Actualizá tu carrito.');
            }
        }

        $total = collect($carrito)->sum(fn($i) => $precios[$i['libro_id']]->precio_venta * $i['cantidad']);

        try {
            $result = DB::transaction(function () use ($request, $carrito, $sucursal_id, $total, $precios) {
                // Verificar stock
                foreach ($carrito as $item) {
                    $stock = Stock::where('libro_id', $item['libro_id'])
                        ->where('sucursal_id', $sucursal_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$stock || $stock->cantidad_disponible < $item['cantidad']) {
                        throw new \RuntimeException(
                            "Stock insuficiente para el libro '{$item['titulo']}' en la sucursal seleccionada."
                        );
                    }
                }

                $cliente = \App\Models\Cliente::where('user_id', Auth::id())->lockForUpdate()->first();
                $clienteId = $cliente ? $cliente->id : null;

                // Definir estado inicial de la venta
                $estado = 'pendiente_pago';
                $motivo_pendiente = $request->tipo_envio === 'acumulacion' ? 'Acumulación' : null;

                $montoMercadoPago = $total;
                $montoCC = 0;

                if ($request->medio_pago === 'Cuenta Corriente' && $cliente) {
                    $excedenteMetodo = $request->input('metodo_pago_excedente');
                    $nuevoSaldoProyectado = $cliente->saldo_actual - $total;

                    if ($nuevoSaldoProyectado >= 0) {
                        // Saldo suficiente, se paga todo con Cuenta Corriente
                        $montoCC = $total;
                        $montoMercadoPago = 0;
                        $estado = 'en_preparacion';
                    } else {
                        // Saldo insuficiente
                        if (in_array($excedenteMetodo, ['Tarjeta', 'Transferencia'])) {
                            $montoCC = max(0, $cliente->saldo_actual);
                            $montoMercadoPago = $total - $montoCC;
                            $estado = 'pendiente_pago';
                        } elseif ($excedenteMetodo === 'Efectivo') {
                            $montoCC = max(0, $cliente->saldo_actual);
                            $montoMercadoPago = 0;
                            $estado = 'pendiente_pago';
                        } else {
                            // Dejar todo como deuda en Cuenta Corriente
                            $montoCC = $total;
                            $montoMercadoPago = 0;
                            $estado = 'en_preparacion';
                        }
                    }
                } elseif ($request->medio_pago === 'Efectivo') {
                    $montoMercadoPago = 0;
                }

                $venta = Venta::create([
                    'fecha'           => now(),
                    'cliente_id'      => $clienteId,
                    'user_id'         => Auth::id(),
                    'sucursal_id'     => $sucursal_id,
                    'tipo'            => 'online',
                    'origen'          => 'online',
                    'total'           => $total,
                    'estado'          => $estado,
                    'tipo_envio'      => $request->tipo_envio,
                    'direccion_envio' => $request->direccion_envio,
                    'motivo_pendiente'=> $motivo_pendiente,
                    'pago_expira_at'  => $estado === 'pendiente_pago' ? now()->addHours(24) : null,
                ]);

                foreach ($carrito as $item) {
                    $precio = $precios[$item['libro_id']]->precio_venta;
                    $costo = $precios[$item['libro_id']]->precio_compra;
                    $venta->detalles()->create([
                        'libro_id'        => $item['libro_id'],
                        'cantidad'        => $item['cantidad'],
                        'precio_unitario' => $precio,
                        'costo_unitario'  => $costo,
                        'subtotal'        => $precio * $item['cantidad'],
                    ]);
                }

                if ($montoCC > 0) {
                    $venta->transacciones()->create([
                        'fecha'        => now(),
                        'tipo'         => 'ingreso',
                        'monto'        => $montoCC,
                        'metodo_pago'  => 'Cuenta Corriente',
                        'sucursal_id'  => $sucursal_id,
                        'user_id'      => Auth::id(),
                        'descripcion'  => "[Pedido Online #{$venta->id}] - Cobrado de Cuenta Corriente",
                    ]);
                    $cliente->decrement('saldo_actual', $montoCC);
                }

                // Si se resolvió el pago inmediatamente (con Cuenta Corriente), descontar stock ya mismo
                if ($estado === 'en_preparacion') {
                    foreach ($carrito as $item) {
                        Stock::where('libro_id', $item['libro_id'])
                            ->where('sucursal_id', $sucursal_id)
                            ->decrement('cantidad_disponible', $item['cantidad']);
                    }
                }

                return [
                    'venta' => $venta,
                    'montoMercadoPago' => $montoMercadoPago,
                ];
            });
        } catch (\Throwable $e) {
            \Log::error('checkout.store: error en transaccion', ['class' => get_class($e), 'msg' => $e->getMessage()]);
            return redirect()->route('carrito.index')
                ->with('error', $e->getMessage());
        }

        $venta = $result['venta'];
        $montoMercadoPago = $result['montoMercadoPago'];

        if ($montoMercadoPago <= 0) {
            return redirect()->route('checkout.success', ['external_reference' => $venta->id]);
        }

        try {
            if ($montoMercadoPago == $total) {
                $items = collect($carrito)->map(fn($item) => [
                    'id'          => (string) $item['libro_id'],
                    'title'       => $item['titulo'],
                    'quantity'    => (int) $item['cantidad'],
                    'unit_price'  => (float) $precios[$item['libro_id']]->precio_venta,
                    'currency_id' => 'ARS',
                ])->values()->toArray();
            } else {
                $items = [
                    [
                        'id'          => 'remainder_' . $venta->id,
                        'title'       => 'Pago excedente del pedido #' . $venta->id,
                        'quantity'    => 1,
                        'unit_price'  => (float) $montoMercadoPago,
                        'currency_id' => 'ARS',
                    ]
                ];
            }

            $baseUrl = config('services.mercadopago.tunnel_url') ?? config('app.url');

            $preferenceData = [
                'items'              => $items,
                'payer'              => ['email' => 'test_user_buyer@testuser.com'],
                'external_reference' => (string) $venta->id,
                'notification_url'   => $baseUrl . '/checkout/webhook',
                'back_urls'          => [
                    'success' => $baseUrl . '/checkout/success',
                    'failure' => $baseUrl . '/checkout/failure',
                    'pending' => $baseUrl . '/checkout/pending',
                ],
            ];

            $response = Http::timeout(15)
                ->withToken(config('services.mercadopago.access_token'))
                ->post('https://api.mercadopago.com/checkout/preferences', $preferenceData);

            if (!$response->successful()) {
                throw new \RuntimeException('MP API error: ' . $response->body());
            }

            $preference = $response->json();

            $venta->update(['payment_id' => $preference['id']]);

            $url = config('services.mercadopago.sandbox')
                ? $preference['sandbox_init_point']
                : $preference['init_point'];

            return Inertia::location($url);

        } catch (\Exception $e) {
            $venta->update(['estado' => 'cancelado']);
            \Log::error('Checkout: error al crear preferencia MP', [
                'venta_id' => $venta->id,
                'error'    => $e->getMessage(),
            ]);
            return redirect()->route('checkout.index')
                ->with('error', 'Error al conectar con Mercado Pago. Intentá nuevamente.');
        }
    }

    public function success(Request $request)
    {
        $ventaId = $request->query('external_reference');
        $venta   = null;

        if ($ventaId) {
            $venta = Venta::where('user_id', Auth::id())->find($ventaId);
            if ($venta) {
                // Limpiar carrito siempre que el pago haya avanzado,
                // independientemente de si el webhook ya llegó o no
                session()->forget('carrito');
            }
        }

        return Inertia::render('Checkout/Confirmacion', [
            'status' => 'success',
            'venta'  => $venta ? [
                'id'         => $venta->id,
                'total'      => $venta->total,
                'tipo_envio' => $venta->tipo_envio,
            ] : null,
        ]);
    }

    public function pending(Request $request)
    {
        $ventaId = $request->query('external_reference');
        $venta   = $ventaId
            ? Venta::where('user_id', Auth::id())->find($ventaId)
            : null;

        return Inertia::render('Checkout/Confirmacion', [
            'status' => 'pending',
            'venta'  => $venta ? ['id' => $venta->id, 'total' => $venta->total] : null,
        ]);
    }

    public function failure(Request $request)
    {
        $ventaId = $request->query('external_reference');

        if ($ventaId) {
            Venta::where('id', $ventaId)
                ->where('user_id', Auth::id())
                ->where('estado', 'pendiente_pago')
                ->update(['estado' => 'cancelado']);
        }

        return Inertia::render('Checkout/Confirmacion', [
            'status' => 'failure',
            'venta'  => null,
        ]);
    }

    public function webhook(Request $request)
    {
        if (!$this->isValidMpSignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        if ($request->type === 'payment' && $request->data) {
            try {
                $paymentId = $request->data['id'] ?? null;
                if (!$paymentId) return response()->json(['ok' => true]);

                $payment = (new PaymentClient())->get($paymentId);
                $ventaId = $payment->external_reference ?? null;

                if (!$ventaId) return response()->json(['ok' => true]);

                $venta = Venta::with('detalles')->find($ventaId);
                if (!$venta) return response()->json(['ok' => true]);

                match ($payment->status) {
                    'approved' => $this->handleApproved($venta, (string) $paymentId),
                    'rejected', 'cancelled' => $venta->estado === 'pendiente_pago'
                        ? $venta->update(['estado' => 'cancelado'])
                        : null,
                    default => null,
                };

            } catch (\Exception $e) {
                \Log::error('Webhook MP: excepción al procesar notificación', [
                    'error'      => $e->getMessage(),
                    'payment_id' => $paymentId ?? null,
                    'venta_id'   => $ventaId ?? null,
                ]);
                // Siempre retornamos 200 a MP para evitar reintentos infinitos
            }
        }

        return response()->json(['ok' => true]);
    }

    private function handleApproved(Venta $venta, string $paymentId): void
    {
        DB::transaction(function () use ($venta, $paymentId) {
            $fresh = Venta::with('detalles')->lockForUpdate()->find($venta->id);

            if (!$fresh || $fresh->estado !== 'pendiente_pago') return;

            // Verificar stock suficiente antes de descontar para prevenir overselling.
            // Si dos compradores pagaron el mismo libro con stock = 1, el segundo
            // pago llega acá con stock ya en 0 y se cancela.
            foreach ($fresh->detalles as $detalle) {
                $stock = Stock::where('libro_id', $detalle->libro_id)
                    ->where('sucursal_id', $fresh->sucursal_id)
                    ->lockForUpdate()
                    ->first();

                if (!$stock || $stock->cantidad_disponible < $detalle->cantidad) {
                    $fresh->update(['estado' => 'cancelado', 'payment_id' => $paymentId]);
                    return;
                }
            }

            $fresh->update([
                'estado'     => 'en_preparacion',
                'payment_id' => $paymentId,
            ]);

            foreach ($fresh->detalles as $detalle) {
                Stock::where('libro_id', $detalle->libro_id)
                    ->where('sucursal_id', $fresh->sucursal_id)
                    ->decrement('cantidad_disponible', $detalle->cantidad);
            }
        });
    }

    private function isValidMpSignature(Request $request): bool
    {
        $secret = config('services.mercadopago.webhook_secret');

        if (empty($secret)) {
            return true;
        }

        $xSignature = $request->header('x-signature');
        $xRequestId = $request->header('x-request-id');

        if (!$xSignature || !$xRequestId) {
            return false;
        }

        // Extraer ts y v1 del header x-signature (formato: "ts=<ts>,v1=<hash>")
        $parts = [];
        foreach (explode(',', $xSignature) as $part) {
            [$key, $value] = explode('=', $part, 2);
            $parts[trim($key)] = trim($value);
        }

        if (empty($parts['ts']) || empty($parts['v1'])) {
            return false;
        }

        $dataId    = $request->input('data.id', '');
        $manifest  = "id:{$dataId};request-id:{$xRequestId};ts:{$parts['ts']};";
        $expected  = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $parts['v1']);
    }
}
