<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibroMaster extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'libro_masters';

    protected $fillable = ['titulo', 'portada', 'autor_id', 'categoria_id', 'proveedor_id', 'idioma_id', 'formato', 'synopsis', 'activo'];

    protected $appends = ['portada_url'];

    public function getStockTotalAttribute(): int
    {
        return $this->libros->sum(fn($libro) => $libro->stocks->sum('cantidad_disponible'));
    }

    public function getPortadaUrlAttribute(): string
    {
        $portada = strtolower(trim((string)$this->portada));

        if (!$this->portada || $portada === '' || $portada === 'null' || str_contains($portada, 'no-cover') || str_contains($portada, 'default') || str_contains($portada, 'generico') || str_contains($portada, 'generica')) {
            return asset('images/no-cover.png');
        }

        if (filter_var($this->portada, FILTER_VALIDATE_URL)) {
            return $this->portada;
        }

        return asset('storage/' . ltrim($this->portada, '/'));
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Autor::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function idioma(): BelongsTo
    {
        return $this->belongsTo(Idioma::class);
    }

    public function libros(): HasMany
    {
        return $this->hasMany(Libro::class, 'master_id');
    }

    public function suscripciones(): HasMany
    {
        return $this->hasMany(Suscripcion::class, 'libro_master_id');
    }
}
