<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Editorial extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'editoriales';

    protected $fillable = [
        'nombre', 'razon_social', 'tipo', 'pais_id', 
        'calle', 'numero', 'piso', 'departamento', 
        'codigo_postal', 'telefono', 'email', 'activo'
    ];

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class);
    }

    public function libros(): HasMany
    {
        return $this->hasMany(Libro::class);
    }
}
