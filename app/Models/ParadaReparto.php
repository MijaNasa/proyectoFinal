<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParadaReparto extends Model
{
    use HasFactory;

    protected $table = 'paradas_reparto';

    protected $fillable = [
        'ruta_reparto_id', 'venta_id', 'estado', 
        'latitud', 'longitud', 'orden', 'observaciones'
    ];

    public function ruta(): BelongsTo
    {
        return $this->belongsTo(RutaReparto::class, 'ruta_reparto_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }
}
