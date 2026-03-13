<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoCliente extends Model
{
    use HasFactory;

    protected $table = 'tipos_clientes';

    protected $fillable = ['codigo', 'nombre', 'descuento_porcentaje', 'activo'];

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }
}
