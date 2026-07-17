<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferenciaStock extends Model
{
    protected $table = 'transferencias_stock';

    protected $fillable = [
        'venta_id',
        'libro_id',
        'sucursal_origen_id',
        'sucursal_destino_id',
        'cantidad',
        'estado',
        'motivo',
        'user_id',
        'fecha',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function libro()
    {
        return $this->belongsTo(Libro::class);
    }

    public function sucursalOrigen()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_origen_id');
    }

    public function sucursalDestino()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_destino_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
