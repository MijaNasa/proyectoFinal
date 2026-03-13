<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoMovimientoStock extends Model
{
    use HasFactory;

    protected $table = 'tipos_movimientos_stock';

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'afecta_stock', 'activo'];

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoStock::class, 'tipo_movimiento_id');
    }
}
