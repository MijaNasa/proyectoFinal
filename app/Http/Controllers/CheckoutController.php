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
            
        // Validar si el carrito entero puede ser cubierto por el stock TOTAL de la empresa
        $hayStockTotal = true;
        foreach ($carrito as $item) {
            $stockTotalParaItem = $stocks->where('libro_id', $item['libro_id'])->sum('cantidad_disponible');
            if ($stockTotalParaItem < $item['cantidad']) {
                $hayStockTotal = false;
                break;
            }
        }

        if (!$hayStockTotal) {
            return redirect()->route('carrito.index')
                ->with('error', 'Lo sentimos, algunos productos de tu carrito ya no tienen stock suficiente en la empresa.');
        }

        $sucursales = $sucursales->map(function($sucursal) use ($stocks, $carrito) {
            $tieneStockLocal = true;
            foreach ($carrito as $item) {
                $stockItem = $stocks->where('sucursal_id', $sucursal->id)
                                    ->where('libro_id', $item['libro_id'])
                                    ->first();
                if (!$stockItem || $stockItem->cantidad_disponible < $item['cantidad']) {
                    $tieneStockLocal = false;
                    break;
                }
            }
            $sucursal->tiene_stock_local = $tieneStockLocal;
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
            'sucursal_id'           => 'required|exists:sucursales,id',
            'direccion_envio'       => 'required_if:tipo_envio,domicilio|nullable|string|max:255',
            'medio_pago'            => 'required|in:Efectivo,Tarjeta,Transferencia,Cuenta Corriente',
            'metodo_pago_excedente' => 'nullable|string|in:Efectivo,Tarjeta,Transferencia',
        ], [
            'tipo_envio.required'         => 'Seleccioná el tipo de entrega.',
            'direccion_envio.required_if' => 'Ingresá la dirección de entrega.',
            'sucursal_id.required'        => 'Seleccioná la sucursal de destino.',
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

        $sucursal_id = $request->sucursal_id;

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

        $librosModels = \App\Models\Libro::whereIn('id', $libroIds)->get()->keyBy('id');
        $cliente = \App\Models\Cliente::where('user_id', Auth::id())->first();
        $clienteId = $cliente ? $cliente->id : null;

        // Procesar Descuentos por Suscripción
        $suscripciones = collect();
        $historialCompras = collect();
        
        if ($clienteId) {
            $suscripciones = \App\Models\Suscripcion::where('cliente_id', $clienteId)
                ->where('sucursal_id', $sucursal_id) // Descuento se basa en sucursal destino
                ->where('estado', 'activa')
                ->get()
                ->keyBy('libro_master_id');

            $historialCompras = \App\Models\VentaDetalle::whereHas('venta', function($q) use ($clienteId) {
                $q->where('cliente_id', $clienteId)->where('estado', '!=', 'cancelado');
            })->pluck('libro_id')->unique();
        }

        $processedItems = [];
        $total = 0;

        foreach ($carrito as $item) {
            $libroId = $item['libro_id'];
            $cantidad = $item['cantidad'];
            $precioOriginal = $precios[$libroId]->precio_venta;
            $costoOriginal = $precios[$libroId]->precio_compra;
            $libroModel = $librosModels[$libroId];

            $hasDiscount = false;
            
            if ($clienteId && $suscripciones->has($libroModel->master_id)) {
                $sub = $suscripciones->get($libroModel->master_id);
                if ($libroModel->created_at > $sub->created_at) {
                    if (!$historialCompras->contains($libroId)) {
                        $hasDiscount = true;
                        $historialCompras->push($libroId);
                    }
                }
            }

            if ($hasDiscount) {
                $precioDescuento = $precioOriginal * 0.95;
                $processedItems[] = [
                    'libro_id' => $libroId,
                    'cantidad' => 1,
                    'precio_venta' => $precioDescuento,
                    'precio_compra' => $costoOriginal,
                ];
                $total += $precioDescuento;
                
                if ($cantidad > 1) {
                    $restante = $cantidad - 1;
                    $processedItems[] = [
                        'libro_id' => $libroId,
                        'cantidad' => $restante,
                        'precio_venta' => $precioOriginal,
                        'precio_compra' => $costoOriginal,
                    ];
                    $total += ($restante * $precioOriginal);
                }
            } else {
                $processedItems[] = [
                    'libro_id' => $libroId,
                    'cantidad' => $cantidad,
                    'precio_venta' => $precioOriginal,
                    'precio_compra' => $costoOriginal,
                ];
                $total += ($cantidad * $precioOriginal);
            }
        }

        try {
            $result = DB::transaction(function () use ($request, $processedItems, $sucursal_id, $total, $cliente) {
                // Verificar Stock TOTAL y Lock
                $requerido = [];
                foreach ($processedItems as $item) {
                    $requerido[$item['libro_id']] = ($requerido[$item['libro_id']] ?? 0) + $item['cantidad'];
                }

                $stocks = Stock::whereIn('libro_id', array_keys($requerido))
                    ->lockForUpdate()
                    ->get()
                    ->groupBy('libro_id');

                foreach ($requerido as $libroId => $cant) {
                    $stockGlobal = isset($stocks[$libroId]) ? $stocks[$libroId]->sum('cantidad_disponible') : 0;
                    if ($stockGlobal < $cant) {
                        throw new \RuntimeException("Stock global insuficiente para procesar la orden.");
                    }
                }

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

                foreach ($processedItems as $item) {
                    $venta->detalles()->create([
                        'libro_id'        => $item['libro_id'],
                        'cantidad'        => $item['cantidad'],
                        'precio_unitario' => $item['precio_venta'],
                        'costo_unitario'  => $item['precio_compra'],
                        'subtotal'        => $item['precio_venta'] * $item['cantidad'],
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

                // Si se resolvió el pago inmediatamente (con Cuenta Corriente), hacer los traslados y descuentos
                if ($estado === 'en_preparacion' || $estado === 'esperando_traslado') {
                    $requiereTraslados = false;

                    foreach ($requerido as $libroId => $cantFaltante) {
                        // 1. Tratar de cubrir con stock local
                        $stockLocal = $stocks[$libroId]->where('sucursal_id', $sucursal_id)->first();
                        
                        if ($stockLocal && $stockLocal->cantidad_disponible > 0) {
                            $aDescontarLocal = min($stockLocal->cantidad_disponible, $cantFaltante);
                            $stockLocal->decrement('cantidad_disponible', $aDescontarLocal);
                            $cantFaltante -= $aDescontarLocal;
                        }

                        // 2. Si todavía falta, pedir traslados a otras sucursales
                        if ($cantFaltante > 0) {
                            $requiereTraslados = true;
                            $otrasSucursales = $stocks[$libroId]->where('sucursal_id', '!=', $sucursal_id)
                                ->sortByDesc('cantidad_disponible');

                            foreach ($otrasSucursales as $otroStock) {
                                if ($cantFaltante <= 0) break;
                                if ($otroStock->cantidad_disponible <= 0) continue;

                                $aTrasladar = min($otroStock->cantidad_disponible, $cantFaltante);
                                
                                // Descontar inmediatamente de la otra sucursal
                                $otroStock->decrement('cantidad_disponible', $aTrasladar);
                                
                                // Crear registro de traslado
                                \App\Models\TransferenciaStock::create([
                                    'venta_id'            => $venta->id,
                                    'libro_id'            => $libroId,
                                    'sucursal_origen_id'  => $otroStock->sucursal_id,
                                    'sucursal_destino_id' => $sucursal_id,
                                    'cantidad'            => $aTrasladar,
                                    'motivo'              => "Traslado automático por Venta Web #{$venta->id}",
                                    'estado'              => 'pendiente',
                                    'user_id'             => Auth::id(),
                                    'fecha'               => now(),
                                ]);

                                $cantFaltante -= $aTrasladar;
                            }
                        }
                    }

                    if ($requiereTraslados) {
                        $venta->update(['estado' => 'esperando_traslado']);
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

            $requiereTraslados = false;
            
            // Agrupar requerimientos
            $requerido = [];
            foreach ($fresh->detalles as $detalle) {
                $requerido[$detalle->libro_id] = ($requerido[$detalle->libro_id] ?? 0) + $detalle->cantidad;
            }

            $stocks = Stock::whereIn('libro_id', array_keys($requerido))
                ->lockForUpdate()
                ->get()
                ->groupBy('libro_id');

            foreach ($requerido as $libroId => $cantFaltante) {
                // 1. Tratar de cubrir con stock local
                $stockLocal = $stocks[$libroId]->where('sucursal_id', $fresh->sucursal_id)->first();
                
                if ($stockLocal && $stockLocal->cantidad_disponible > 0) {
                    $aDescontarLocal = min($stockLocal->cantidad_disponible, $cantFaltante);
                    $stockLocal->decrement('cantidad_disponible', $aDescontarLocal);
                    $cantFaltante -= $aDescontarLocal;
                }

                // 2. Si todavía falta, pedir traslados
                if ($cantFaltante > 0) {
                    $requiereTraslados = true;
                    $otrasSucursales = $stocks[$libroId]->where('sucursal_id', '!=', $fresh->sucursal_id)
                        ->sortByDesc('cantidad_disponible');

                    foreach ($otrasSucursales as $otroStock) {
                        if ($cantFaltante <= 0) break;
                        if ($otroStock->cantidad_disponible <= 0) continue;

                        $aTrasladar = min($otroStock->cantidad_disponible, $cantFaltante);
                        $otroStock->decrement('cantidad_disponible', $aTrasladar);
                        
                        \App\Models\TransferenciaStock::create([
                            'venta_id'            => $fresh->id,
                            'libro_id'            => $libroId,
                            'sucursal_origen_id'  => $otroStock->sucursal_id,
                            'sucursal_destino_id' => $fresh->sucursal_id,
                            'cantidad'            => $aTrasladar,
                            'motivo'              => "Traslado automático por Venta Web #{$fresh->id}",
                            'estado'              => 'pendiente',
                            'user_id'             => Auth::id() ?? 1,
                            'fecha'               => now(),
                        ]);

                        $cantFaltante -= $aTrasladar;
                    }
                }
            }

            $fresh->update([
                'estado'     => $requiereTraslados ? 'esperando_traslado' : 'en_preparacion',
                'payment_id' => $paymentId,
            ]);
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
