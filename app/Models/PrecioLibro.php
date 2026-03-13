<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrecioLibro extends Model
{
    use HasFactory;

    protected $table = 'precios_libros';

    protected $fillable = [
        'libro_id', 'precio_compra', 'precio_venta', 
        'fecha_desde', 'fecha_hasta', 'motivo', 'activo'
    ];

    public function libro(): BelongsTo
    {
        return $this->belongsTo(Libro::class);
    }
}
