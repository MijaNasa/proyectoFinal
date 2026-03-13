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

    protected $fillable = ['titulo', 'titulo_original', 'autor_id', 'categoria_id', 'activo'];

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
