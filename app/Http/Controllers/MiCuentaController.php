<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MiCuentaController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $cliente = $user->cliente;

        // Dos pestañas: pendientes (todo lo que no termino) y finalizados.
        // Los cancelados no se le muestran al cliente.
        $pestana = $request->input('pestana') === 'finalizados' ? 'finalizados' : 'pendientes';
        $basePedidos = fn () => Venta::query()
            ->when($cliente, fn($q) => $q->where('cliente_id', $cliente->id), fn($q) => $q->whereRaw('1 = 0'))
            ->where('estado', '!=', 'cancelado');
        $conteoPedidos = [
            'pendientes'  => $basePedidos()->where('estado', '!=', 'finalizado')->count(),
            'finalizados' => $basePedidos()->where('estado', 'finalizado')->count(),
        ];

        // Las ventas de un cliente se identifican por cliente_id, no por user_id:
        // en una venta presencial user_id es el empleado que la cobró, no el
        // cliente. Sin esto, las compras de mostrador nunca aparecían acá.
        // Pedido -> cobro de envio (Correo Nacional) que lo incluye, para marcarlo en la lista
        $envioConjuntoDe = [];
        if ($cliente) {
            $cliente->ventas()
                ->whereNotNull('consolida_venta_ids')
                ->where('estado', '!=', 'cancelado')
                ->get(['id', 'consolida_venta_ids'])
                ->each(function ($cobro) use (&$envioConjuntoDe) {
                    foreach (array_filter(array_map('intval', explode(',', $cobro->consolida_venta_ids))) as $id) {
                        $envioConjuntoDe[$id] = $cobro->id;
                    }
                });
        }

        $pedidos = Venta::with(['detalles.libro.master:id,titulo', 'sucursal:id,nombre', 'transacciones'])
            ->when($cliente, fn($q) => $q->where('cliente_id', $cliente->id), fn($q) => $q->whereRaw('1 = 0'))
            ->where('estado', '!=', 'cancelado')
            ->when($pestana === 'finalizados',
                fn($q) => $q->where('estado', 'finalizado'),
                fn($q) => $q->where('estado', '!=', 'finalizado'))
            ->latest('fecha')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn($v) => [
                'id'         => $v->id,
                'es_cobro_envio'    => $v->detalles->isEmpty() && (float) $v->costo_envio > 0,
                // Pago con saldo a favor (total o parcial) y lo que falta pagar
                'pagado_saldo'      => (float) $v->transacciones->where('tipo', 'ingreso')->where('metodo_pago', 'Cuenta Corriente')->sum('monto'),
                'restante'          => max(0, round((float) $v->total - (float) $v->transacciones->where('tipo', 'ingreso')->sum('monto'), 2)),
                'pedidos_incluidos' => $v->consolida_venta_ids
                    ? array_values(array_filter(array_map('intval', explode(',', $v->consolida_venta_ids))))
                    : [],
                'envio_conjunto_id' => $envioConjuntoDe[$v->id] ?? null,
                'fecha'      => $v->fecha,
                'total'      => $v->total,
                'estado'          => $v->estado,
                'tipo'            => $v->tipo,
                'metodo_pago'     => $v->metodo_pago,
                'comprobante_path'=> $v->comprobante_path ? Storage::url($v->comprobante_path) : null,
                'tipo_envio'      => $v->tipo_envio,
                'direccion_envio' => $v->direccion_envio,
                'tracking_code'   => $v->tracking_code,
                'sucursal_nombre' => $v->sucursal->nombre ?? 'N/A',
                'items'           => $v->detalles->map(fn($d) => [
                    'titulo'   => ($d->libro->master->titulo ?? 'Libro') . ' - Tomo ' . ($d->libro->numero_tomo ?? 'Único'),
                    'cantidad' => $d->cantidad,
                    'subtotal' => $d->subtotal,
                ]),
            ]);

        $pagos = $cliente
            ? $cliente->transacciones()
                ->where('tipo', 'ingreso')
                ->latest('fecha')
                ->get(['id', 'monto', 'metodo_pago', 'fecha', 'descripcion'])
            : collect();

        // Pedidos acumulados listos para enviar (de todas las paginas, no solo la actual)
        $acumuladosListos = $cliente
            ? $this->acumuladosListosQuery($cliente)
                ->with('detalles.libro.master:id,titulo')
                ->orderBy('fecha')
                ->get()
                ->map(fn ($v) => [
                    'id'    => $v->id,
                    'fecha' => $v->fecha,
                    'items' => $v->detalles->map(fn ($d) => [
                        'titulo'   => ($d->libro->master->titulo ?? 'Libro') . ' - Tomo ' . ($d->libro->numero_tomo ?? 'Único'),
                        'cantidad' => $d->cantidad,
                    ]),
                ])
            : collect();

        return Inertia::render('MiCuenta/Index', [
            'pedidos' => $pedidos,
            'pestana' => $pestana,
            'conteoPedidos' => $conteoPedidos,
            'acumuladosListos' => $acumuladosListos,
            'pagos'   => $pagos,
            'usuario' => [
                'name'       => $user->name,
                'apellido'   => $user->apellido,
                'email'      => $user->email,
                'created_at' => $user->created_at,
            ],
            'saldoCuenta' => $cliente ? (float) $cliente->saldo_actual : null,
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ], [
            'current_password.required' => 'Ingresá tu contraseña actual.',
            'password.required'         => 'Ingresá la nueva contraseña.',
            'password.min'              => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.confirmed'        => 'Las contraseñas no coinciden.',
        ]);

        if (!Hash::check($request->current_password, Auth::user()->password)) {
            return back()->withErrors(['current_password' => 'La contraseña actual es incorrecta.']);
        }

        Auth::user()->update(['password' => Hash::make($request->password)]);

        return back()->with('message', 'Contraseña actualizada correctamente.');
    }

    public function uploadComprobante(Request $request, Venta $venta)
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($venta->user_id !== $user->id && $venta->cliente?->user_id !== $user->id && !$user->empleado) {
                return back()->with('error', 'No tienes permisos para modificar este pedido.');
            }
        } else {
            if ($venta->estado !== 'pendiente_pago') {
                return back()->with('error', 'No se puede subir comprobante a un pedido que no está pendiente de pago.');
            }
            if (session('checkout_venta_id') && session('checkout_venta_id') != $venta->id) {
                return back()->with('error', 'Sesión de pedido inválida.');
            }
        }

        $request->validate([
            'comprobante' => 'required|file|mimes:jpeg,png,jpg,webp,pdf|max:7168', // Max 7MB
        ], [
            'comprobante.required' => 'Seleccioná un archivo de comprobante.',
            'comprobante.file'     => 'El archivo seleccionado no es válido.',
            'comprobante.mimes'    => 'El comprobante debe ser una imagen (JPG, PNG, WEBP) o un documento PDF.',
            'comprobante.max'      => 'El archivo es demasiado grande (máximo 7 MB). Por favor elegí una imagen o captura más liviana.',
        ]);

        if ($request->hasFile('comprobante')) {
            // Delete old file if exists
            if ($venta->comprobante_path) {
                Storage::disk('public')->delete($venta->comprobante_path);
                
                \Illuminate\Notifications\DatabaseNotification::where('type', \App\Notifications\ComprobanteSubido::class)
                    ->where('data', 'like', '%"url":"/ventas?search=' . $venta->id . '"%')
                    ->delete();
            }

            $path = $request->file('comprobante')->store('comprobantes', 'public');
            $venta->update(['comprobante_path' => $path]);

            // Notify employees
            $users = \App\Models\User::whereHas('empleado')->get();
            foreach ($users as $user) {
                $user->notify(new \App\Notifications\ComprobanteSubido($venta));
            }
        }

        return back()->with('message', 'Comprobante subido exitosamente. Un empleado verificará el pago a la brevedad.');
    }

    public function viewComprobante(Request $request, Venta $venta)
    {
        $autorizado = false;

        if (Auth::check()) {
            $user = Auth::user();
            if ($user->esAdmin() || $user->esGerente() || $user->empleado || $venta->user_id === $user->id || $venta->cliente?->user_id === $user->id) {
                $autorizado = true;
            }
        } else {
            if (session('checkout_venta_id') == $venta->id) {
                $autorizado = true;
            }
        }

        if (!$autorizado) {
            abort(403, 'No tienes permisos para ver este comprobante.');
        }

        if (!$venta->comprobante_path || !Storage::disk('public')->exists($venta->comprobante_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($venta->comprobante_path));
    }

    public function deleteComprobante(Request $request, Venta $venta)
    {
        $autorizado = false;

        if (Auth::check()) {
            $user = Auth::user();
            if ($user->esAdmin() || $user->esGerente() || $user->empleado || $venta->user_id === $user->id || $venta->cliente?->user_id === $user->id) {
                $autorizado = true;
            }
        } else {
            if (session('checkout_venta_id') == $venta->id) {
                $autorizado = true;
            }
        }

        if (!$autorizado) {
            return back()->with('error', 'No tienes permisos para eliminar este comprobante.');
        }

        if ($venta->comprobante_path) {
            Storage::disk('public')->delete($venta->comprobante_path);
            $venta->update(['comprobante_path' => null]);
            
            // Eliminar notificación vieja
            \Illuminate\Notifications\DatabaseNotification::where('type', \App\Notifications\ComprobanteSubido::class)
                ->where('data', 'like', '%"url":"/ventas?search=' . $venta->id . '"%')
                ->delete();
        }

        return back()->with('message', 'Comprobante eliminado exitosamente.');
    }

    /**
     * El propio cliente solicita el envio de todos sus pedidos acumulados
     * juntos, en vez de que un empleado tenga que hacerlo por el. Si el envio
     * cae fuera del Gran Rosario (Correo Nacional) tiene un costo fijo que se
     * cobra aparte, con el mismo circuito de pago que una compra online -
     * los pedidos recien se consolidan cuando ESE pago se confirma.
     */
    public function consolidarAcumulados(Request $request)
    {
        $request->validate([
            'modo'            => 'required|in:domicilio,correo_nacional',
            'direccion_envio' => 'required|string|max:255',
            'latitud'         => 'nullable|numeric|between:-90,90',
            'longitud'        => 'nullable|numeric|between:-180,180',
            'medio_pago'      => 'required_if:modo,correo_nacional|nullable|in:Tarjeta,Transferencia',
        ]);

        $user = Auth::user();
        $cliente = $user->cliente;
        if (!$cliente) {
            abort(403);
        }

        // Solo pedidos ya pagos ('acumulado') y que no esten en otro cobro de envio pendiente
        $idsAcumulados = $this->acumuladosListosQuery($cliente)->pluck('id')->toArray();

        if (empty($idsAcumulados)) {
            return back()->withErrors(['error' => 'No tenés pedidos acumulados listos para enviar.']);
        }

        if ($request->modo === 'domicilio') {
            $cantidad = $cliente->consolidarAcumulados('domicilio', $request->direccion_envio, $request->latitud, $request->longitud, $idsAcumulados);

            if ($cantidad === 0) {
                return back()->withErrors(['error' => 'No tenés pedidos acumulados listos para enviar.']);
            }

            $this->notificarSolicitudEnvio($cliente, $cantidad, 'domicilio');

            return back()->with('message', "Se solicitó el envío de {$cantidad} pedido(s) acumulado(s). Te vamos a avisar cuando esté en camino.");
        }

        // Correo Nacional: hay un costo de envio fijo a cobrar antes de despachar.
        $costoEnvio = 50000;
        $sucursalPrincipal = \App\Models\Sucursal::where('es_principal', true)->first() ?? \App\Models\Sucursal::first();

        $ventaEnvio = Venta::create([
            'fecha'               => now(),
            'cliente_id'          => $cliente->id,
            'user_id'             => $user->id,
            'sucursal_id'         => $sucursalPrincipal?->id,
            'tipo'                => 'online',
            'total'               => $costoEnvio,
            'costo_envio'         => $costoEnvio,
            'estado'              => 'pendiente_pago',
            'tipo_envio'          => 'correo_nacional',
            'direccion_envio'     => $request->direccion_envio,
            'latitud'             => $request->latitud,
            'longitud'            => $request->longitud,
            'metodo_pago'         => $request->medio_pago,
            'consolida_venta_ids' => implode(',', $idsAcumulados),
            'pago_expira_at'      => now()->addHours($request->medio_pago === 'Transferencia' ? 12 : 6),
        ]);

        if ($request->medio_pago === 'Transferencia') {
            $this->notificarSolicitudEnvio($cliente, count($idsAcumulados), 'correo_nacional', $ventaEnvio);
            return back()->with('message', 'Generamos el cobro del envío. Subí el comprobante de transferencia desde "Mis Pedidos" para confirmar el despacho.');
        }

        // Mercado Pago
        $mpToken = config('services.mercadopago.access_token');
        if (empty($mpToken)) {
            if (config('app.env') === 'local' || config('app.debug')) {
                \Log::info("MiCuenta: MP_ACCESS_TOKEN no configurado en local. Simulando pago de envío aprobado para venta #{$ventaEnvio->id}");
                $ventaEnvio->update(['estado' => 'en_preparacion']);
                $ventaEnvio->procesarConsolidacionAcumuladosSiCorresponde();
                return back()->with('message', 'Envío confirmado (modo simulado, sin Mercado Pago configurado).');
            }
            $ventaEnvio->update(['estado' => 'cancelado']);
            return back()->withErrors(['error' => 'El pago con Mercado Pago no está disponible temporalmente. Probá con Transferencia Bancaria.']);
        }

        try {
            $baseUrl = config('services.mercadopago.tunnel_url') ?? config('app.url');

            $preferenceData = [
                'items' => [[
                    'id'          => 'envio_acumulados_' . $ventaEnvio->id,
                    'title'       => 'Envío de pedidos acumulados #' . $ventaEnvio->id,
                    'quantity'    => 1,
                    'unit_price'  => (float) $costoEnvio,
                    'currency_id' => 'ARS',
                ]],
                'payer'              => ['email' => $user->email],
                'external_reference' => (string) $ventaEnvio->id,
                'notification_url'   => $baseUrl . '/checkout/webhook',
                'back_urls'          => [
                    'success' => $baseUrl . '/checkout/success',
                    'failure' => $baseUrl . '/checkout/failure',
                    'pending' => $baseUrl . '/checkout/pending',
                ],
            ];

            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->withToken($mpToken)
                ->post('https://api.mercadopago.com/checkout/preferences', $preferenceData);

            if (!$response->successful()) {
                throw new \RuntimeException('MP API error: ' . $response->body());
            }

            $preference = $response->json();
            $ventaEnvio->update(['payment_id' => $preference['id']]);
            $this->notificarSolicitudEnvio($cliente, count($idsAcumulados), 'correo_nacional', $ventaEnvio);

            $url = config('services.mercadopago.sandbox')
                ? $preference['sandbox_init_point']
                : $preference['init_point'];

            return Inertia::location($url);
        } catch (\Exception $e) {
            $ventaEnvio->update(['estado' => 'cancelado']);
            \Log::error('MiCuenta: error al crear preferencia MP para envío de acumulados', [
                'venta_id' => $ventaEnvio->id,
                'error'    => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'Error al conectar con Mercado Pago. Probá con Transferencia Bancaria.']);
        }
    }

    /**
     * Pedidos acumulados del cliente listos para enviar: pagos (estado
     * 'acumulado') y que no esten ya incluidos en un cobro de envio por
     * Correo Nacional que todavia esta pendiente de pago.
     */
    private function acumuladosListosQuery($cliente)
    {
        $idsEnCobroPendiente = $cliente->ventas()
            ->whereNotNull('consolida_venta_ids')
            ->where('estado', 'pendiente_pago')
            ->pluck('consolida_venta_ids')
            ->flatMap(fn ($ids) => array_map('intval', explode(',', $ids)))
            ->filter()
            ->all();

        return $cliente->ventas()
            ->where('motivo_pendiente', 'Acumulación')
            ->where('estado', 'acumulado')
            ->when($idsEnCobroPendiente, fn ($q) => $q->whereNotIn('id', $idsEnCobroPendiente));
    }

    private function notificarSolicitudEnvio($cliente, int $cantidad, string $modo, ?Venta $cobroEnvio = null): void
    {
        try {
            \Illuminate\Support\Facades\Notification::send(
                \App\Notifications\SolicitudEnvioAcumulados::destinatarios(),
                new \App\Notifications\SolicitudEnvioAcumulados($cliente, $cantidad, $modo, $cobroEnvio)
            );
        } catch (\Throwable $e) {
            // Un error al notificar no debe frenar la solicitud del cliente
            \Log::warning('MiCuenta: no se pudo notificar la solicitud de envío de acumulados', ['error' => $e->getMessage()]);
        }
    }
}
