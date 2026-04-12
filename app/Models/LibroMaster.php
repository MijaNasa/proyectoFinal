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

    protected $fillable = ['titulo', 'titulo_original', 'portada', 'autor_id', 'categoria_id', 'activo'];

    protected $appends = ['portada_url'];

    public function getPortadaUrlAttribute()
    {
        if (!$this->portada) {
            return 'https://via.placeholder.com/400x600?text=Sin+Portada';
        }

        if (filter_var($this->portada, FILTER_VALIDATE_URL)) {
            return $this->portada;
        }

        return asset('storage/' . $this->portada);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Autor::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function libros(): HasMany
    {
        return $this->hasMany(Libro::class, 'master_id');
    }
}
