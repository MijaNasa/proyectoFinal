<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Gasto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'concepto', 'categoria', 'monto', 'fecha',
        'metodo_pago', 'comprobante', 'observaciones',
        'sucursal_id', 'user_id',
    ];

    protected $casts = ['fecha' => 'date'];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaccion(): MorphOne
    {
        return $this->morphOne(Transaccion::class, 'transaccionable');
    }
}
