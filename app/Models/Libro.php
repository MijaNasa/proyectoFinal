<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Libro extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'isbn', 'master_id', 'editorial_id', 'idioma_id', 
        'año_edicion', 'cantidad_paginas', 'synopsis', 'activo'
    ];

    public function master(): BelongsTo
    {
        return $this->belongsTo(LibroMaster::class, 'master_id');
    }

    public function editorial(): BelongsTo
    {
        return $this->belongsTo(Editorial::class);
    }

    public function idioma(): BelongsTo
    {
        return $this->belongsTo(Idioma::class);
    }

    public function precios(): HasMany
    {
        return $this->hasMany(PrecioLibro::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function getPrecioActualAttribute()
    {
        return $this->precios()->where('activo', true)
            ->where(function ($query) {
                $query->whereNull('fecha_hasta')
                    ->orWhere('fecha_hasta', '>', now());
            })->latest('fecha_desde')->first();
    }
}
