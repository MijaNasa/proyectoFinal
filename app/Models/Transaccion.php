<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaccion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'transacciones';

    protected $fillable = [
        'tipo', 'monto', 'metodo_pago', 'fecha', 'sucursal_id', 
        'transaccionable_id', 'transaccionable_type', 
        'descripcion', 'user_id'
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function transaccionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
