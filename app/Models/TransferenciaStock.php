<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TransferenciaStock extends Model
{
    use HasFactory;

    protected $table = 'transferencias_stock';

    protected $fillable = [
        'libro_id', 'sucursal_origen_id', 'sucursal_destino_id',
        'cantidad', 'motivo', 'user_id', 'fecha',
    ];

    protected $casts = ['fecha' => 'date'];

    public function libro(): BelongsTo
    {
        return $this->belongsTo(Libro::class);
    }

    public function sucursalOrigen(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_origen_id');
    }

    public function sucursalDestino(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_destino_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movimientos(): MorphMany
    {
        return $this->morphMany(MovimientoStock::class, 'referencia');
    }
}
