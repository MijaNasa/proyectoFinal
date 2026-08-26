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
            'en_preventa',
            'acumulado',
            'cancelado'
        ],
        'en_preventa' => [
            'en_preparacion',
            'cancelado'
        ],
        'en_preparacion' => [
            'listo_para_retiro',
            'enviado',
            'esperando_traslado',
            'cancelado'
        ],
        'esperando_traslado' => [
            'listo_para_retiro',
            'enviado',
            'cancelado'
        ],
        'acumulado' => [
            'en_preparacion',
            'cancelado'
        ],
        'listo_para_retiro' => [
            'finalizado'
        ],
        'enviado' => [
            'finalizado',
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
        'latitud', 'longitud'
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

    public function paradas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ParadaReparto::class);
    }

    public function cancelarConRestitucionDeStock()
    {
        if ($this->estado === 'cancelado') {
            return;
        }

        // Si la venta está asociada a traslados (TransferenciaStock)
        $traslados = \App\Models\TransferenciaStock::where('venta_id', $this->id)->get();
        
        foreach ($this->detalles as $detalle) {
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

        // Devolver saldo si usó cuenta corriente
        if ($this->cliente) {
            $montoPagado = $this->transacciones()->where('tipo', 'ingreso')->sum('monto');
            if ($montoPagado > 0) {
                $this->cliente->increment('saldo_actual', $montoPagado);
            }
        }

        $this->update(['estado' => 'cancelado']);
    }
}
