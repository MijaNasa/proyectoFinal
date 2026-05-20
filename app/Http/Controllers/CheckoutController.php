<?php

namespace App\Http\Controllers;

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

        $total = collect($carrito)->sum(fn($i) => $i['precio'] * $i['cantidad']);

        $venta = DB::transaction(function () use ($request, $carrito, $sucursal, $total) {
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
                $venta->detalles()->create([
                    'libro_id'        => $item['libro_id'],
                    'cantidad'        => $item['cantidad'],
                    'precio_unitario' => $item['precio'],
                    'subtotal'        => $item['precio'] * $item['cantidad'],
                ]);
            }

            return $venta;
        });

        try {
            $client = new PreferenceClient();

            $items = collect($carrito)->map(fn($item) => [
                'id'          => (string) $item['libro_id'],
                'title'       => $item['titulo'],
                'quantity'    => (int) $item['cantidad'],
                'unit_price'  => (float) $item['precio'],
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
        $ventaId = $request->query('external_reference');
        $venta   = $ventaId ? Venta::with('detalles')->find($ventaId) : null;

        if ($venta && $venta->estado === 'pendiente_pago') {
            DB::transaction(function () use ($venta, $request) {
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
        $venta   = $ventaId ? Venta::find($ventaId) : null;

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
        if ($venta->estado !== 'pendiente_pago') return;

        DB::transaction(function () use ($venta, $paymentId) {
            $venta->update([
                'estado'     => 'en_preparacion',
                'payment_id' => $paymentId,
            ]);

            foreach ($venta->detalles as $detalle) {
                Stock::where('libro_id', $detalle->libro_id)
                    ->where('sucursal_id', $venta->sucursal_id)
                    ->decrement('cantidad_disponible', $detalle->cantidad);
            }
        });
    }
}
