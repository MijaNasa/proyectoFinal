<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'proveedores';

    protected $fillable = [
        'nombre_empresa', 'nombre_contacto', 'telefono', 'email', 'direccion', 'activo',
    ];

    public function series(): HasMany
    {
        return $this->hasMany(Serie::class);
    }
}
