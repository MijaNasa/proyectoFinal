<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrdenCompra extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'numero_orden', 'proveedor_id', 'sucursal_id', 'estado',
        'fecha', 'fecha_entrega_estimada', 'total', 'observaciones', 'user_id',
    ];

    protected $casts = [
        'fecha'                  => 'date',
        'fecha_entrega_estimada' => 'date',
    ];

    public function proveedor()   { return $this->belongsTo(Proveedor::class); }
    public function sucursal()    { return $this->belongsTo(Sucursal::class); }
    public function user()        { return $this->belongsTo(User::class); }
    public function items()       { return $this->hasMany(OrdenCompraItem::class, 'orden_compra_id'); }
}
