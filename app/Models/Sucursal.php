<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

class Sucursal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sucursales';

    protected $fillable = [
        'nombre', 'calle', 'numero', 'piso',
        'departamento', 'codigo_postal', 'ciudad_id',
        'telefono', 'email', 'activo', 'es_principal'
    ];

    protected $casts = [
        'activo' => 'boolean',
        'es_principal' => 'boolean',
    ];

    protected function nombre(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Str::title(mb_strtolower($value, 'UTF-8')) : null,
            set: fn ($value) => $value ? Str::title(mb_strtolower($value, 'UTF-8')) : null,
        );
    }

    protected function calle(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Str::title(mb_strtolower($value, 'UTF-8')) : null,
            set: fn ($value) => $value ? Str::title(mb_strtolower($value, 'UTF-8')) : null,
        );
    }

    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }
}
