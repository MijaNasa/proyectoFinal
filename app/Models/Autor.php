<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

class Autor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'autores';

    protected $fillable = [
        'nombre', 'apellido', 'pais_id', 
        'fecha_nacimiento', 'biografia', 'activo'
    ];

    protected function nombre(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Str::title(mb_strtolower($value, 'UTF-8')) : null,
            set: fn ($value) => $value ? Str::title(mb_strtolower($value, 'UTF-8')) : null,
        );
    }

    protected function apellido(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Str::title(mb_strtolower($value, 'UTF-8')) : null,
            set: fn ($value) => $value ? Str::title(mb_strtolower($value, 'UTF-8')) : null,
        );
    }

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class);
    }

    public function libroMasters(): HasMany
    {
        return $this->hasMany(LibroMaster::class);
    }
}
