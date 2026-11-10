<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Venta extends Model
{
    use HasFactory, SoftDeletes;

    public const TRANSICIONES = [
        'pendiente_pago' => [
            'en_preparacion',
            'listo_para_retiro',
            'finalizado',
            'en_preventa',
            'acumulado',
            'cancelado'
        ],
        'en_preventa' => [
            'en_preparacion',
            'listo_para_retiro',
            'acumulado',
            'cancelado'
        ],
        'en_preparacion' => [
            'listo_para_retiro',
            'enviado',
            'cancelado'
        ],
        'esperando_traslado' => [
            'en_preparacion',
            'listo_para_retiro',
            'cancelado'
        ],
        'acumulado' => [
            'en_preparacion',
            'listo_para_retiro',
            'cancelado'
        ],
        'listo_para_retiro' => [
            'finalizado'
        ],
        'enviado' => [
            'en_preparacion'
        ],
        'finalizado' => [],
        'cancelado'  => [],
    ];

    protected $fillable = [
        'fecha', 'cliente_id', 'user_id',
        'sucursal_id', 'tipo', 'total',
        'estado', 'tipo_envio', 'direccion_envio',
        'pago_expira_at', 'payment_id',
        'origen', 'motivo_pendiente', 'metodo_pago', 'comprobante_path',
        'latitud', 'longitud',
        'tracking_code', 'costo_envio', 'consolida_venta_ids'
    ];

    protected $casts = [
        'pago_expira_at' => 'datetime',
    ];

    protected $appends = ['atendido_por'];

    protected static function booted()
    {
        static::saved(function ($venta) {
            $direccionCambio = $venta->wasChanged('direccion_envio') || ($venta->wasRecentlyCreated && $venta->direccion_envio);
            // Si ya llegaron coordenadas desde el autocomplete (Photon) no hace falta re-geocodificar.
            if ($direccionCambio && $venta->tipo_envio === 'domicilio' && !$venta->latitud) {
                \App\Jobs\GeocodeAddressJob::dispatch($venta);
            }
        });
    }

    public function getAtendidoPorAttribute()
    {
        if ($this->user) {
            return $this->user;
        }

        if ($this->sucursal_id) {
            $adminEmpleado = Empleado::where('sucursal_id', $this->sucursal_id)
                ->whereHas('cargos', function ($q) {
                    $q->where('nombre', 'ADMIN');
                })
                ->with('user:id,name,apellido')
                ->first();
            
            if ($adminEmpleado && $adminEmpleado->user) {
                return $adminEmpleado->user;
            }
        }
        
        return null;
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function transacciones(): MorphMany
    {
        return $this->morphMany(Transaccion::class, 'transaccionable');
    }

    /**
     * Si esta venta es el cobro del envio de pedidos acumulados (ver
     * MiCuentaController::consolidarAcumulados), una vez que SU pago se
     * confirma (Mercado Pago aprobado o transferencia confirmada por un
     * empleado) se consolidan recien ahi las ventas que quedaron en espera.
     */
    public function procesarConsolidacionAcumuladosSiCorresponde(): void
    {
        if (!$this->consolida_venta_ids) {
            return;
        }

        $ids = array_filter(array_map('intval', explode(',', $this->consolida_venta_ids)));
        if (!empty($ids) && $this->cliente) {
            $this->cliente->consolidarAcumulados(
                $this->tipo_envio,
                $this->direccion_envio,
                $this->latitud,
                $this->longitud,
                $ids
            );
        }

        // No se borra consolida_venta_ids: queda como registro de que pedidos
        // incluyo este cobro de envio (se muestra en Ventas y en Mis Pedidos).
        // No hay riesgo de reprocesar: solo se llama al confirmar un pago
        // pendiente, y consolidarAcumulados() solo toma pedidos en 'acumulado'.
    }

    /**
     * Si la venta esta "en preventa" y la sucursal de la venta ya tiene stock
     * de todos sus libros en preventa, descuenta ese stock y la pasa al estado
     * que sigue (en preparacion, listo para retiro, acumulado o esperando
     * traslado). Si falta stock de alguno, no toca nada y la venta sigue en
     * preventa hasta la proxima recepcion de mercaderia.
     *
     * Se llama al recibir una orden de compra y al confirmar el pago (manual
     * o Mercado Pago), asi no importa en que orden pasen las dos cosas.
     * Debe ejecutarse dentro de una transaccion.
     */
    public function intentarLiberarPreventa(bool $notificarTraslados = true): bool
    {
        if ($this->estado !== 'en_preventa') {
            return false;
        }

        $this->loadMissing('detalles.libro');

        // Cantidad necesaria por libro (solo los items en preventa)
        $necesario = [];
        foreach ($this->detalles as $detalle) {
            if ($detalle->libro && $detalle->libro->permite_preventa) {
                $necesario[$detalle->libro_id] = ($necesario[$detalle->libro_id] ?? 0) + $detalle->cantidad;
            }
        }

        if (empty($necesario)) {
            return false;
        }

        // Solo stock de la propia sucursal de la venta
        $stocks = [];
        foreach ($necesario as $libroId => $cantidad) {
            $stock = Stock::where('libro_id', $libroId)
                ->where('sucursal_id', $this->sucursal_id)
                ->lockForUpdate()
                ->first();

            if (!$stock || $stock->cantidad_disponible < $cantidad) {
                return false;
            }
            $stocks[$libroId] = $stock;
        }

        foreach ($necesario as $libroId => $cantidad) {
            $stocks[$libroId]->decrement('cantidad_disponible', $cantidad);
        }

        $tieneTraslados = TransferenciaStock::where('venta_id', $this->id)->exists();
        $nuevoEstado = $tieneTraslados ? 'esperando_traslado' : 'en_preparacion';

        if (!$tieneTraslados) {
            if ($this->tipo_envio === 'retiro') {
                $nuevoEstado = 'listo_para_retiro';
            } elseif ($this->tipo_envio === 'acumulacion') {
                $nuevoEstado = 'acumulado';
            }
        }

        $this->update(['estado' => $nuevoEstado]);

        if ($tieneTraslados) {
            TransferenciaStock::where('venta_id', $this->id)
                ->where('estado', 'pendiente')
                ->update(['estado' => 'pendiente_envio']);
        }

        if ($tieneTraslados && $notificarTraslados) {
            $usuariosNotificar = \App\Notifications\TrasladoPendienteVenta::destinatarios($this);
            \Illuminate\Support\Facades\Notification::send($usuariosNotificar, new \App\Notifications\TrasladoPendienteVenta($this));
        }

        return true;
    }

    /**
     * Cobro del envio de pedidos acumulados (Correo Nacional): no tiene
     * productos propios y guarda en consolida_venta_ids que pedidos incluye.
     * Funciona como "el paquete": su estado y su codigo de seguimiento se
     * copian a todos los pedidos incluidos.
     */
    public function esCobroEnvio(): bool
    {
        return !empty($this->consolida_venta_ids);
    }

    public function idsPedidosIncluidos(): array
    {
        return $this->consolida_venta_ids
            ? array_values(array_filter(array_map('intval', explode(',', $this->consolida_venta_ids))))
            : [];
    }

    /** Cobro de envio (no cancelado) que incluye a esta venta, si existe. */
    public function cobroEnvioQueLaIncluye(): ?Venta
    {
        return static::whereNotNull('consolida_venta_ids')
            ->where('estado', '!=', 'cancelado')
            ->where('id', '!=', $this->id)
            ->whereRaw("(',' || consolida_venta_ids || ',') LIKE ?", ['%,' . $this->id . ',%'])
            ->first();
    }

    /** Mapa [id de pedido => id del cobro de envio que lo incluye], para listados. */
    public static function mapaEnvioConjunto(): array
    {
        $mapa = [];
        static::whereNotNull('consolida_venta_ids')
            ->where('estado', '!=', 'cancelado')
            ->get(['id', 'consolida_venta_ids'])
            ->each(function ($cobro) use (&$mapa) {
                foreach ($cobro->idsPedidosIncluidos() as $id) {
                    $mapa[$id] = $cobro->id;
                }
            });
        return $mapa;
    }

    /**
     * Copia el codigo de seguimiento y el estado de despacho del cobro a los
     * pedidos que incluye. Al despachar (Finalizado con codigo) finaliza a
     * todos; si despues se corrige el codigo, se actualiza en todos.
     */
    public function propagarEnvioAPedidosIncluidos(): void
    {
        $ids = $this->idsPedidosIncluidos();
        if (empty($ids)) {
            return;
        }

        $pedidos = static::whereIn('id', $ids)->where('estado', '!=', 'cancelado')->get();

        foreach ($pedidos as $pedido) {
            $cambios = ['tracking_code' => $this->tracking_code];

            if ($this->estado === 'finalizado' && in_array($pedido->estado, ['en_preparacion', 'enviado'])) {
                // Correo despachado (con codigo): los pedidos tambien quedan finalizados
                $cambios['estado'] = 'finalizado';
            } elseif ($this->estado === 'enviado' && in_array($pedido->estado, ['en_preparacion', 'enviado'])) {
                $cambios['estado'] = 'enviado';
            } elseif ($this->estado === 'en_preparacion' && $pedido->estado === 'enviado') {
                $cambios['estado'] = 'en_preparacion';
            }

            $pedido->update($cambios);
        }
    }

    public function paradas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ParadaReparto::class);
    }

    /**
     * Devuelve al cliente el saldo de cuenta corriente que se uso para pagar
     * esta venta (pago total o parcial con saldo a favor). Se usa cuando el
     * pedido se cancela antes de que se tocara el stock (ej: fallo al abrir
     * Mercado Pago).
     */
    public function devolverSaldoUsado(): void
    {
        if (!$this->cliente) {
            return;
        }

        $monto = (float) $this->transacciones()
            ->where('tipo', 'ingreso')
            ->where('metodo_pago', 'Cuenta Corriente')
            ->sum('monto');

        if ($monto > 0) {
            $this->cliente->increment('saldo_actual', $monto);
        }
    }

    /**
     * @param bool $acreditarOtrosPagos Si es true, lo pagado por otros medios
     *   (transferencia, Mercado Pago, efectivo) tambien se acredita como saldo a
     *   favor. Lo pagado con saldo de cuenta corriente vuelve siempre.
     */
    public function cancelarConRestitucionDeStock(bool $acreditarOtrosPagos = false)
    {
        if ($this->estado === 'cancelado') {
            return;
        }

        // Si la venta está asociada a traslados (TransferenciaStock)
        $traslados = \App\Models\TransferenciaStock::where('venta_id', $this->id)->get();

        $this->loadMissing('detalles.libro');

        // Solo se devuelve al stock lo que realmente se desconto:
        // - Online con Mercado Pago todavia sin aprobar: no se desconto nada
        //   (el stock se descuenta recien cuando MP aprueba el pago).
        // - Tomos en preventa: se descuentan recien cuando la preventa se libera,
        //   asi que mientras la venta siga pendiente o esperando preventa, no se devuelven.
        $mpSinAprobar = $this->tipo === 'online'
            && $this->estado === 'pendiente_pago'
            && !in_array($this->metodo_pago, ['Efectivo', 'Transferencia', 'Cuenta Corriente']);
        $preventaSinLiberar = in_array($this->estado, ['pendiente_pago', 'en_preventa']);

        foreach ($this->detalles as $detalle) {
            $esPreventa = $this->tipo === 'online' && $detalle->libro && $detalle->libro->permite_preventa;
            if ($esPreventa ? $preventaSinLiberar : $mpSinAprobar) {
                continue;
            }

            $trasladado = $traslados->where('libro_id', $detalle->libro_id)->sum('cantidad');
            $local = $detalle->cantidad - $trasladado;
            
            // Restituir lo que se haya descontado de la sucursal local (la de la Venta)
            if ($local > 0) {
                \App\Models\Stock::where('libro_id', $detalle->libro_id)
                    ->where('sucursal_id', $this->sucursal_id)
                    ->increment('cantidad_disponible', $local);
            }
        }

        // Restituir lo que se haya descontado de otras sucursales (origen de los traslados)
        foreach ($traslados as $t) {
            \App\Models\Stock::where('libro_id', $t->libro_id)
                ->where('sucursal_id', $t->sucursal_origen_id)
                ->increment('cantidad_disponible', $t->cantidad);
            
            $t->update(['estado' => 'cancelado', 'motivo' => 'Venta cancelada']);
        }

        // Devolver saldo: lo pagado con cuenta corriente vuelve siempre; lo pagado
        // por otros medios solo si el empleado elige acreditarlo como saldo a favor
        // (si se le devolvio el dinero por afuera, no se acredita).
        if ($this->cliente) {
            $ingresos = $this->transacciones()->where('tipo', 'ingreso');
            $montoADevolver = (float) (clone $ingresos)->where('metodo_pago', 'Cuenta Corriente')->sum('monto');
            if ($acreditarOtrosPagos) {
                $montoADevolver += (float) (clone $ingresos)->where('metodo_pago', '!=', 'Cuenta Corriente')->sum('monto');
            }
            if ($montoADevolver > 0) {
                $this->cliente->increment('saldo_actual', $montoADevolver);
            }
        }

        $this->update(['estado' => 'cancelado']);
    }
}
