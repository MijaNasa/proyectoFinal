<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Serie extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'series';

    protected $fillable = [
        'nombre', 'descripcion', 'proveedor_id', 'activo',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function libros(): HasMany
    {
        return $this->hasMany(Libro::class);
    }
}
