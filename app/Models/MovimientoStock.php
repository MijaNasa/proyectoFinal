<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoStock extends Model
{
    use HasFactory;

    protected $table = 'movimientos_stock';

    protected $fillable = [
        'tipo',
        'sucursal_origen_id',
        'sucursal_destino_id',
        'user_id',
        'motivo',
    ];

    public function origen()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_origen_id');
    }

    public function destino()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_destino_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function detalles()
    {
        return $this->hasMany(MovimientoStockDetalle::class, 'movimiento_id');
    }
}
