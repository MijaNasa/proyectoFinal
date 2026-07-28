<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

class Proveedor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'proveedores';

    protected $fillable = [
        'nombre_empresa', 'nombre_contacto', 'telefono', 'email', 'activo', 'deuda_actual',
    ];

    protected function nombreEmpresa(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Str::title(mb_strtolower($value, 'UTF-8')) : null,
            set: fn ($value) => $value ? Str::title(mb_strtolower($value, 'UTF-8')) : null,
        );
    }

    protected function nombreContacto(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Str::title(mb_strtolower($value, 'UTF-8')) : null,
            set: fn ($value) => $value ? Str::title(mb_strtolower($value, 'UTF-8')) : null,
        );
    }

    public function libroMasters(): HasMany
    {
        return $this->hasMany(LibroMaster::class);
    }

    public function transacciones(): MorphMany
    {
        return $this->morphMany(Transaccion::class, 'transaccionable');
    }
}
