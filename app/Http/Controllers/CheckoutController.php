<?php

namespace App\Http\Controllers;

use App\Models\PrecioLibro;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Models\TipoCliente;
use App\Traits\GeocodeHelper;
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
    use GeocodeHelper;

    public function __construct()
    {
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
    }

    public function index()
    {
        $carrito = session('carrito', []);

        if (empty($carrito)) {
            return redirect()->route('carrito.index')
                ->with('error', 'Tu carrito está vacío.');
        }

        $total = collect($carrito)->sum(fn($i) => $i['precio'] * $i['cantidad']);
        
        $cliente = \App\Models\Cliente::where('user_id', Auth::user()?->id)->first();
        $sucursales = \App\Models\Sucursal::where('activo', true)->get(['id', 'nombre']);

        $libroIds = collect($carrito)->pluck('libro_id');
        $stocks = \App\Models\Stock::whereIn('libro_id', $libroIds)
            ->whereIn('sucursal_id', $sucursales->pluck('id'))
            ->get();
            
        // Validar si el carrito entero puede ser cubierto por el stock TOTAL de la empresa (excepto preventas)
        $hayStockTotal = true;
        foreach ($carrito as $item) {
            $libroModel = \App\Models\Libro::find($item['libro_id']);
            if ($libroModel && $libroModel->permite_preventa) {
                continue;
            }
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
                $libroModel = \App\Models\Libro::find($item['libro_id']);
                if ($libroModel && $libroModel->permite_preventa) {
                    continue;
                }
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

        $sucursalPrincipal = \App\Models\Sucursal::where('es_principal', true)->where('activo', true)->first();
        if (!$sucursalPrincipal && $sucursales->isNotEmpty()) {
            $sucursalPrincipal = $sucursales->first();
        }

        return Inertia::render('Checkout/Index', [
            'items'        => array_values($carrito),
            'total'        => $total,
            'saldo_actual' => $cliente ? $cliente->saldo_actual : 0,
            'sucursales'   => $sucursales,
            'sucursal_principal_id' => $sucursalPrincipal ? $sucursalPrincipal->id : null,
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'tipo_envio'            => 'required|in:retiro,domicilio,acumulacion,correo_nacional',
            'sucursal_id'           => 'required|exists:sucursales,id',
            'direccion_envio'       => 'required_if:tipo_envio,domicilio,correo_nacional|nullable|string|max:255',
            'latitud'               => 'nullable|numeric|between:-90,90',
            'longitud'              => 'nullable|numeric|between:-180,180',
            'medio_pago'            => 'required|in:Efectivo,Tarjeta,Transferencia,Cuenta Corriente',
        ];
        
        $messages = [
            'tipo_envio.required'         => 'Seleccioná el tipo de entrega.',
            'direccion_envio.required_if' => 'Ingresá la dirección de entrega.',
            'sucursal_id.required'        => 'Seleccioná la sucursal de destino.',
            'medio_pago.required'         => 'Seleccioná el método de pago.',
            'guest_nombre.required'       => 'El nombre es obligatorio.',
            'guest_dni.required'          => 'El DNI o documento es obligatorio.',
            'guest_email.required'        => 'El correo electrónico es obligatorio.',
            'guest_telefono.required'     => 'El teléfono es obligatorio.',
        ];

        if (!Auth::check()) {
            $rules = array_merge($rules, [
                'guest_nombre'   => 'required|string|max:255',
                'guest_apellido' => 'nullable|string|max:255',
                'guest_dni'      => 'required|string|max:50',
                'guest_email'    => 'required|email|max:255',
                'guest_telefono' => 'required|string|max:50',
            ]);
        }

        $request->validate($rules, $messages);

        if (in_array($request->tipo_envio, ['domicilio', 'acumulacion'])) {
            if ($request->medio_pago === 'Efectivo') {
                return back()->withErrors(['medio_pago' => 'El pago en Efectivo solo está disponible para retiro en sucursal.']);
            }
        }

        $carrito = session('carrito', []);

        if (empty($carrito)) {
            return redirect()->route('carrito.index')
                ->with('error', 'Tu carrito está vacío.');
        }

        $sucursal_id = $request->sucursal_id;
        
        $costo_envio = 0;
        if (in_array($request->tipo_envio, ['domicilio', 'correo_nacional'])) {
            $sucursalPrincipal = \App\Models\Sucursal::where('es_principal', true)->first();
            if ($sucursalPrincipal) {
                $sucursal_id = $sucursalPrincipal->id;
            }
            if ($request->tipo_envio === 'correo_nacional') {
                $costo_envio = 50000;
            }
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

        $librosModels = \App\Models\Libro::whereIn('id', $libroIds)->get()->keyBy('id');

        // Logic for Guest vs Auth User
        $user = Auth::user();
        $userId = $user ? $user->id : null;
        $cliente = null;

        if (!$userId) {
            $existingUser = \App\Models\User::where('dni', $request->guest_dni)->first();

            if ($existingUser) {
                $userId = $existingUser->id;
                $cliente = $existingUser->cliente;
                if (!$cliente) {
                    $tipoCliente = \App\Models\TipoCliente::where('codigo', 'PART')->first();
                    $cliente = $existingUser->cliente()->create([
                        'tipo_cliente_id' => $tipoCliente ? $tipoCliente->id : 1,
                        'saldo_actual'    => 0,
                    ]);
                }
                // Actualizar correo y teléfono si cambiaron o estaban incompletos
                if ($request->guest_email && $existingUser->email !== $request->guest_email) {
                    // Si el nuevo correo no pertenece a otro usuario, actualizarlo
                    $emailUsado = \App\Models\User::where('email', $request->guest_email)->where('id', '!=', $existingUser->id)->exists();
                    if (!$emailUsado) {
                        $existingUser->update(['email' => $request->guest_email]);
                    }
                }
                if ($request->guest_telefono && empty($existingUser->telefono)) {
                    $existingUser->update(['telefono' => $request->guest_telefono]);
                }
            } else {
                // Crear usuario y perfil de cliente para la compra del invitado
                $newUser = \App\Models\User::create([
                    'name'     => $request->guest_nombre,
                    'apellido' => $request->guest_apellido,
                    'dni'      => $request->guest_dni,
                    'telefono' => $request->guest_telefono,
                    'email'    => $request->guest_email,
                    'password' => \Hash::make($request->guest_dni),
                ]);

                $tipoCliente = \App\Models\TipoCliente::where('codigo', 'PART')->first();
                $cliente = $newUser->cliente()->create([
                    'tipo_cliente_id' => $tipoCliente ? $tipoCliente->id : 1,
                    'saldo_actual'    => 0,
                ]);

                $userId = $newUser->id;
            }
        } else {
            $cliente = \App\Models\Cliente::where('user_id', $userId)->first();
        }

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
            $libroModel = $librosModels[$libroId];
            $precioOriginal = $libroModel->permite_preventa ? $precios[$libroId]->precio_venta * 0.90 : $precios[$libroId]->precio_venta;
            $costoOriginal = $precios[$libroId]->precio_compra;

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

        $total += $costo_envio;

        try {
            $result = DB::transaction(function () use ($request, $processedItems, $sucursal_id, $total, $cliente, $userId, $clienteId, $costo_envio, $librosModels) {
                // Verificar Stock TOTAL y Lock (solo para no preventas)
                $requerido = [];
                $tienePreventas = false;
                foreach ($processedItems as $item) {
                    if ($librosModels[$item['libro_id']]->permite_preventa) {
                        $tienePreventas = true;
                        continue;
                    }
                    $requerido[$item['libro_id']] = ($requerido[$item['libro_id']] ?? 0) + $item['cantidad'];
                }

                $stocks = collect();
                if (!empty($requerido)) {
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
                }

                // Definir estado inicial de la venta
                $estado = 'pendiente_pago';
                $motivo_pendiente = $request->tipo_envio === 'acumulacion' ? 'Acumulación' : null;

                $montoMercadoPago = $total;
                $montoCC = 0;

                if ($request->medio_pago === 'Cuenta Corriente' && $cliente) {
                    if ($cliente->saldo_actual < $total) {
                        throw new \RuntimeException("Saldo insuficiente en Cuenta Corriente para cubrir el total.");
                    }
                    $montoCC = $total;
                    $montoMercadoPago = 0;
                    $estado = 'en_preparacion';
                } elseif (in_array($request->medio_pago, ['Efectivo', 'Transferencia'])) {
                    $montoMercadoPago = 0; 
                    $estado = 'pendiente_pago'; // Se reserva stock abajo
                }

                $venta = Venta::create([
                    'fecha'           => now(),
                    'cliente_id'      => $clienteId,
                    'user_id'         => $userId,
                    'sucursal_id'     => $sucursal_id,
                    'tipo'            => 'online',
                    'origen'          => 'online',
                    'total'           => $total,
                    'estado'          => $estado,
                    'tipo_envio'      => $request->tipo_envio,
                    'direccion_envio' => $request->direccion_envio,
                    'latitud'         => $request->tipo_envio === 'domicilio' ? $request->latitud : null,
                    'longitud'        => $request->tipo_envio === 'domicilio' ? $request->longitud : null,
                    'costo_envio'     => $costo_envio,
                    'motivo_pendiente'=> $motivo_pendiente,
                    'metodo_pago'     => $request->medio_pago,
                    'pago_expira_at'  => ($estado === 'pendiente_pago' && in_array($request->medio_pago, ['Efectivo', 'Transferencia'])) ? now()->addHours(12) : ($estado === 'pendiente_pago' ? now()->addHours(6) : null),
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
                        'user_id'      => $userId,
                        'descripcion'  => "[Pedido Online #{$venta->id}] - Cobrado de Cuenta Corriente",
                    ]);
                    $cliente->decrement('saldo_actual', $montoCC);
                }

                // Si se resolvió el pago inmediatamente o si es Efectivo/Transferencia (reserva), hacer los traslados y descuentos
                $debeDescontar = false;
                if ($estado === 'en_preparacion' || $estado === 'esperando_traslado') {
                    $debeDescontar = true;
                } elseif (in_array($request->medio_pago, ['Efectivo', 'Transferencia'])) {
                    // Si es Transferencia o Efectivo y tiene preventa, no descontamos stock aún
                    if (!$tienePreventas) {
                        $debeDescontar = true;
                    }
                }

                if ($debeDescontar) {
                    $requiereTraslados = false;

                    foreach ($requerido as $libroId => $cantFaltante) {
                        // 1. Tratar de cubrir con stock local
                        $stockLocal = $stocks[$libroId]->where('sucursal_id', $sucursal_id)->first();
                        
                        if ($stockLocal && $stockLocal->cantidad_disponible > 0) {
                            $aDescontarLocal = min($stockLocal->cantidad_disponible, $cantFaltante);
                            $stockLocal->decrement('cantidad_disponible', $aDescontarLocal);
                            $cantFaltante -= $aDescontarLocal;
                        }

                        // Si es pago en efectivo y requiere traslados, arrojar error
                        if ($cantFaltante > 0 && $request->medio_pago === 'Efectivo') {
                            throw new \RuntimeException("Para productos que requieren traslado entre sucursales, es necesario confirmar la compra mediante pago online.");
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
                                    'user_id'             => $userId,
                                    'fecha'               => now(),
                                ]);

                                $cantFaltante -= $aTrasladar;
                            }
                        }
                    }

                    if ($requiereTraslados) {
                        $updateData = [];
                        
                        if ($estado === 'en_preparacion') {
                            $updateData['estado'] = 'esperando_traslado';
                        }
                        
                        if ($estado === 'pendiente_pago' && $request->medio_pago === 'Transferencia') {
                            $updateData['pago_expira_at'] = now()->addHours(2);
                        }
                        
                        if (!empty($updateData)) {
                            $venta->update($updateData);
                        }
                    } else {
                        // Si no requiere traslados y el pago est confirmado
                        if ($estado === 'en_preparacion') {
                            if ($request->tipo_envio === 'retiro') {
                                $venta->update(['estado' => 'listo_para_retiro']);
                            } elseif ($request->tipo_envio === 'acumulacion') {
                                $venta->update(['estado' => 'acumulado']);
                            }
                        }
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
                $items = collect($processedItems)->map(function($item) use ($librosModels) {
                    $libro = $librosModels[$item['libro_id']];
                    return [
                        'id'          => (string) $item['libro_id'],
                        'title'       => $libro->master->titulo . ($libro->numero_tomo ? ' - Tomo ' . $libro->numero_tomo : ''),
                        'quantity'    => (int) $item['cantidad'],
                        'unit_price'  => (float) $item['precio_venta'],
                        'currency_id' => 'ARS',
                    ];
                })->values()->toArray();
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
                'payer'              => ['email' => $request->guest_email ?? (Auth::user()?->email ?? 'guest@tienda.com')],
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
        $venta   = $ventaId ? Venta::find($ventaId) : null;

        if ($venta) {
            // Limpiar carrito siempre que el pago haya avanzado,
            // independientemente de si el webhook ya llegó o no
            session()->forget('carrito');

            // El webhook de MP puede demorar (o fallar por config) en avisarnos el pago;
            // si el usuario ya volvió acá con un payment_id, verificamos directo contra la
            // API de MP en vez de depender exclusivamente del webhook.
            $paymentId = $request->query('payment_id') ?? $request->query('collection_id');
            if ($venta->estado === 'pendiente_pago' && $paymentId) {
                try {
                    $payment = (new PaymentClient())->get($paymentId);
                    if ($payment->status === 'approved') {
                        $this->handleApproved($venta, (string) $paymentId);
                        $venta->refresh();
                    }
                } catch (\Exception $e) {
                    \Log::error('Checkout success: error verificando pago contra MP', [
                        'venta_id'   => $venta->id,
                        'payment_id' => $paymentId,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        }

        return Inertia::render('Checkout/Confirmacion', [
            'status' => 'success',
            'venta'  => $venta ? [
                'id'               => $venta->id,
                'total'            => $venta->total,
                'tipo_envio'       => $venta->tipo_envio,
                'metodo_pago'      => $venta->metodo_pago,
                'estado'           => $venta->estado,
                'comprobante_path' => $venta->comprobante_path,
                'guest_dni'        => $venta->user?->dni ?? $venta->cliente?->user?->dni,
                'guest_email'      => $venta->user?->email ?? $venta->cliente?->user?->email,
            ] : null,
        ]);
    }

    public function pending(Request $request)
    {
        $ventaId = $request->query('external_reference');
        $venta   = $ventaId ? Venta::find($ventaId) : null;

        return Inertia::render('Checkout/Confirmacion', [
            'status' => 'pending',
            'venta'  => $venta ? [
                'id'               => $venta->id,
                'total'            => $venta->total,
                'tipo_envio'       => $venta->tipo_envio,
                'metodo_pago'      => $venta->metodo_pago,
                'estado'           => $venta->estado,
                'comprobante_path' => $venta->comprobante_path,
                'guest_dni'        => $venta->user?->dni ?? $venta->cliente?->user?->dni,
                'guest_email'      => $venta->user?->email ?? $venta->cliente?->user?->email,
            ] : null,
        ]);
    }

    public function failure(Request $request)
    {
        $ventaId = $request->query('external_reference');

        if ($ventaId) {
            $venta = Venta::where('id', $ventaId)
                ->where('estado', 'pendiente_pago')
                ->first();

            if ($venta) {
                $venta->cancelarConRestitucionDeStock();
            }
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
                        ? clone($venta)->cancelarConRestitucionDeStock()
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
            $fresh = Venta::with(['detalles', 'detalles.libro'])->lockForUpdate()->find($venta->id);

            if (!$fresh || $fresh->estado !== 'pendiente_pago') return;

            $requiereTraslados = false;
            $tienePreventas = false;
            
            // Agrupar requerimientos (solo para los que NO son preventa)
            $requerido = [];
            foreach ($fresh->detalles as $detalle) {
                if ($detalle->libro && $detalle->libro->permite_preventa) {
                    $tienePreventas = true;
                    continue; // NO descontamos stock ni pedimos traslados para preventas an
                }
                $requerido[$detalle->libro_id] = ($requerido[$detalle->libro_id] ?? 0) + $detalle->cantidad;
            }

            if (!empty($requerido)) {
                $stocks = Stock::whereIn('libro_id', array_keys($requerido))
                    ->lockForUpdate()
                    ->get()
                    ->groupBy('libro_id');

                foreach ($requerido as $libroId => $cantFaltante) {
                    // 1. Tratar de cubrir con stock local
                    if (isset($stocks[$libroId])) {
                        $stockLocal = $stocks[$libroId]->where('sucursal_id', $fresh->sucursal_id)->first();
                        
                        if ($stockLocal && $stockLocal->cantidad_disponible > 0) {
                            $aDescontarLocal = min($stockLocal->cantidad_disponible, $cantFaltante);
                            $stockLocal->decrement('cantidad_disponible', $aDescontarLocal);
                            $cantFaltante -= $aDescontarLocal;
                        }

                        // 2. Si todava falta, pedir traslados
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
                                    'motivo'              => "Traslado automtico por Venta Web #{$fresh->id}",
                                    'estado'              => 'pendiente_envio',
                                    'user_id'             => Auth::user()?->id ?? 1,
                                    'fecha'               => now(),
                                ]);

                                $cantFaltante -= $aTrasladar;
                            }
                        }
                    }
                }
            }

            if ($tienePreventas) {
                $nuevoEstado = 'en_preventa';
            } else {
                $nuevoEstado = $requiereTraslados ? 'esperando_traslado' : 'en_preparacion';
                if (!$requiereTraslados) {
                    if ($fresh->tipo_envio === 'retiro') {
                        $nuevoEstado = 'listo_para_retiro';
                    } elseif ($fresh->tipo_envio === 'acumulacion') {
                        $nuevoEstado = 'acumulado';
                    }
                }
            }

            $fresh->update([
                'estado'     => $nuevoEstado,
                'payment_id' => $paymentId,
            ]);

            if ($requiereTraslados) {
                $usuariosNotificar = \App\Models\User::where('activo', true)->get()->filter(fn($u) => $u->esAdmin() || $u->esGerente());
                \Illuminate\Support\Facades\Notification::send($usuariosNotificar, new \App\Notifications\TrasladoPendienteVenta($fresh));
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
