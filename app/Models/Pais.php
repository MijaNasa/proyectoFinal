<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pais extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'paises';

    protected $fillable = ['nombre', 'codigo', 'activo'];

    public function provincias(): HasMany
    {
        return $this->hasMany(Provincia::class);
    }

    public function autores(): HasMany
    {
        return $this->hasMany(Autor::class);
    }

    public function editoriales(): HasMany
    {
        return $this->hasMany(Editorial::class);
    }
}
