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
}
