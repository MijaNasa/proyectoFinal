<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

class Ciudad extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ciudades';

    protected $fillable = ['nombre', 'codigo_postal', 'provincia_id', 'activo'];

    protected function nombre(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Str::title(mb_strtolower($value, 'UTF-8')) : null,
            set: fn ($value) => $value ? Str::title(mb_strtolower($value, 'UTF-8')) : null,
        );
    }

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class);
    }

    public function sucursales(): HasMany
    {
        return $this->hasMany(Sucursal::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
