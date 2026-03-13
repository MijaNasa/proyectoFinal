<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MovimientoStock extends Model
{
    use HasFactory;

    protected $table = 'movimientos_stock';

    protected $fillable = [
        'stock_id', 'tipo_movimiento_id', 'cantidad', 
        'cantidad_anterior', 'cantidad_nueva', 'motivo', 
        'referencia_id', 'referencia_type', 'user_id', 
        'fecha_movimiento', 'activo'
    ];

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function tipoMovimiento(): BelongsTo
    {
        return $this->belongsTo(TipoMovimientoStock::class, 'tipo_movimiento_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referencia(): MorphTo
    {
        return $this->morphTo();
    }
}
