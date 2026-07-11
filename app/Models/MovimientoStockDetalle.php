<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoStockDetalle extends Model
{
    use HasFactory;

    protected $table = 'movimiento_stock_detalles';

    protected $fillable = [
        'movimiento_id',
        'libro_id',
        'cantidad',
        'costo_unitario',
    ];

    public function movimiento()
    {
        return $this->belongsTo(MovimientoStock::class, 'movimiento_id');
    }

    public function libro()
    {
        return $this->belongsTo(Libro::class);
    }
}
