<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Idioma extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'idiomas';

    protected $fillable = ['nombre', 'codigo', 'activo'];

    public function libros(): HasMany
    {
        return $this->hasMany(Libro::class);
    }
}
