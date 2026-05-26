<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenCompraItem extends Model
{
    protected $fillable = [
        'orden_compra_id', 'libro_id', 'cantidad', 'precio_unitario', 'subtotal',
    ];

    public function libro()      { return $this->belongsTo(Libro::class); }
    public function ordenCompra(){ return $this->belongsTo(OrdenCompra::class, 'orden_compra_id'); }
}
