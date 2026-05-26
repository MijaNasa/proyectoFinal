<?php

namespace App\Http\Controllers;

use App\Models\PrecioLibro;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
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
            return redirect()->route('login')
                ->with('warning', 'Iniciá sesión para continuar con tu compra.');
        }

        $carrito = session('carrito', []);

        if (empty($carrito)) {
            return redirect()->route('carrito.index')
                ->with('error', 'Tu carrito está vacío.');
        }

        $total = collect($carrito)->sum(fn($i) => $i['precio'] * $i['cantidad']);

        return Inertia::render('Checkout/Index', [
            'items' => array_values($carrito),
            'total' => $total,
        ]);
    }

    public function store(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $request->validate([
            'tipo_envio'      => 'required|in:retiro,domicilio',
            'direccion_envio' => 'required_if:tipo_envio,domicilio|nullable|string|max:255',
        ], [
            'tipo_envio.required'       => 'Seleccioná el tipo de entrega.',
            'direccion_envio.required_if' => 'Ingresá la dirección de entrega.',
        ]);

        $carrito = session('carrito', []);

        if (empty($carrito)) {
            return redirect()->route('carrito.index')
                ->with('error', 'Tu carrito está vacío.');
        }

        $sucursal = Sucursal::where('es_deposito_central', true)->where('activo', true)->first()
            ?? Sucursal::where('activo', true)->first();

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
            $venta = DB::transaction(function () use ($request, $carrito, $sucursal, $total, $precios) {
                foreach ($carrito as $item) {
                    $stock = Stock::where('libro_id', $item['libro_id'])
                        ->where('sucursal_id', $sucursal->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$stock || $stock->cantidad_disponible < $item['cantidad']) {
                        throw new \RuntimeException(
                            "Stock insuficiente para el libro ID {$item['libro_id']}."
                        );
                    }
                }

                $venta = Venta::create([
                    'fecha'           => now(),
                    'user_id'         => Auth::id(),
                    'sucursal_id'     => $sucursal->id,
                    'tipo'            => 'online',
                    'total'           => $total,
                    'estado'          => 'pendiente_pago',
                    'tipo_envio'      => $request->tipo_envio,
                    'direccion_envio' => $request->direccion_envio,
                    'pago_expira_at'  => now()->addHours(24),
                ]);

                foreach ($carrito as $item) {
                    $precio = $precios[$item['libro_id']]->precio_venta;
                    $venta->detalles()->create([
                        'libro_id'        => $item['libro_id'],
                        'cantidad'        => $item['cantidad'],
                        'precio_unitario' => $precio,
                        'subtotal'        => $precio * $item['cantidad'],
                    ]);
                }

                return $venta;
            });
        } catch (\RuntimeException $e) {
            return redirect()->route('carrito.index')
                ->with('error', $e->getMessage() . ' Actualizá tu carrito.');
        }

        try {
            $client = new PreferenceClient();

            $items = collect($carrito)->map(fn($item) => [
                'id'          => (string) $item['libro_id'],
                'title'       => $item['titulo'],
                'quantity'    => (int) $item['cantidad'],
                'unit_price'  => (float) $precios[$item['libro_id']]->precio_venta,
                'currency_id' => 'ARS',
            ])->values()->toArray();

            $preference = $client->create([
                'items'              => $items,
                'payer'              => ['email' => Auth::user()->email],
                'back_urls'          => [
                    'success' => route('checkout.success'),
                    'pending' => route('checkout.pending'),
                    'failure' => route('checkout.failure'),
                ],
                'auto_return'        => 'approved',
                'external_reference' => (string) $venta->id,
                'notification_url'   => route('checkout.webhook'),
                'expires'            => true,
                'expiration_date_to' => $venta->pago_expira_at->toIso8601String(),
            ]);

            $venta->update(['payment_id' => $preference->id]);

            $url = config('services.mercadopago.sandbox')
                ? $preference->sandbox_init_point
                : $preference->init_point;

            return redirect()->away($url);

        } catch (MPApiException $e) {
            $venta->update(['estado' => 'cancelado']);
            return redirect()->route('checkout.index')
                ->with('error', 'Error al conectar con Mercado Pago. Intentá nuevamente.');
        }
    }

    public function success(Request $request)
    {
        $ventaId  = $request->query('external_reference');
        $ventaOut = null;

        if ($ventaId) {
            DB::transaction(function () use ($ventaId, $request, &$ventaOut) {
                $venta = Venta::with('detalles')
                    ->where('user_id', Auth::id())
                    ->lockForUpdate()
                    ->find($ventaId);

                if (!$venta) return;

                $ventaOut = $venta;

                if ($venta->estado !== 'pendiente_pago') return;

                $venta->update([
                    'estado'     => 'en_preparacion',
                    'payment_id' => $request->query('payment_id') ?? $venta->payment_id,
                ]);

                foreach ($venta->detalles as $detalle) {
                    Stock::where('libro_id', $detalle->libro_id)
                        ->where('sucursal_id', $venta->sucursal_id)
                        ->decrement('cantidad_disponible', $detalle->cantidad);
                }
            });

            session()->forget('carrito');
        }

        return Inertia::render('Checkout/Confirmacion', [
            'status' => 'success',
            'venta'  => $ventaOut ? [
                'id'         => $ventaOut->id,
                'total'      => $ventaOut->total,
                'tipo_envio' => $ventaOut->tipo_envio,
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
                    'rejected', 'cancelled' => $venta->where('estado', 'pendiente_pago')
                        ->update(['estado' => 'cancelado']),
                    default => null,
                };

            } catch (\Exception) {
                // Siempre retornamos 200 a MP
            }
        }

        return response()->json(['ok' => true]);
    }

    private function handleApproved(Venta $venta, string $paymentId): void
    {
        DB::transaction(function () use ($venta, $paymentId) {
            $fresh = Venta::with('detalles')->lockForUpdate()->find($venta->id);

            if (!$fresh || $fresh->estado !== 'pendiente_pago') return;

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
            // En producción, rechazar si no hay secret configurado
            return !app()->isProduction();
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
