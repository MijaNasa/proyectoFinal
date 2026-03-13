<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Autor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'autores';

    protected $fillable = [
        'nombre', 'apellido', 'pais_id', 
        'fecha_nacimiento', 'biografia', 'activo'
    ];

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class);
    }

    public function libroMasters(): HasMany
    {
        return $this->hasMany(LibroMaster::class);
    }
}
