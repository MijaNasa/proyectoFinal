<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Cliente extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'tipo_cliente_id', 'saldo_actual', 
        'preferencias', 'estado_abono'
    ];

    protected $casts = [
        'preferencias' => 'json'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tipoCliente(): BelongsTo
    {
        return $this->belongsTo(TipoCliente::class, 'tipo_cliente_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function compras()
    {
        return $this->hasMany(Venta::class, 'cliente_id');
    }

    public function suscripciones()
    {
        return $this->hasMany(Suscripcion::class);
    }

    public function transacciones(): MorphMany
    {
        return $this->morphMany(Transaccion::class, 'transaccionable');
    }

    /**
     * Consolida los pedidos en estado de Acumulacion de este cliente en una
     * sola entrega (envio a domicilio o retiro en sucursal). Usado tanto por
     * el empleado/gerente desde la ficha del cliente como por el propio
     * cliente desde "Mis Pedidos" en Mi Cuenta.
     *
     * @return int cantidad de pedidos consolidados
     */
    public function consolidarAcumulados(string $modo, ?string $direccionEnvio = null, ?float $latitud = null, ?float $longitud = null, ?array $soloIds = null): int
    {
        // Acumulacion es exclusivamente para coordinar un envio conjunto
        // (domicilio local o Correo Nacional); no existe un modo "retiro".
        // Solo se consolidan ventas con el pago ya confirmado (estado
        // 'acumulado'): una venta en Acumulación nace en 'pendiente_pago' y
        // recien pasa a 'acumulado' cuando confirmarPago() la confirma (o
        // cuando llega el traslado de stock, si hizo falta). Si todavia esta
        // impaga, se queda afuera y espera el proximo intento de envio.
        $ventas = $this->ventas()
            ->where('motivo_pendiente', 'Acumulación')
            ->where('estado', 'acumulado')
            ->when($soloIds, fn($q) => $q->whereIn('id', $soloIds))
            ->lockForUpdate()
            ->get();

        if ($ventas->isEmpty()) {
            return 0;
        }

        \DB::transaction(function () use ($ventas, $modo, $direccionEnvio, $latitud, $longitud) {
            foreach ($ventas as $venta) {
                $venta->update([
                    'tipo_envio' => $modo,
                    'motivo_pendiente' => null,
                    'estado' => 'en_preparacion',
                    'direccion_envio' => $direccionEnvio,
                    'latitud' => $latitud,
                    'longitud' => $longitud,
                ]);
            }
        });

        return $ventas->count();
    }
}
