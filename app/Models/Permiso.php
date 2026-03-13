<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permiso extends Model
{
    use HasFactory;

    protected $fillable = ['codigo', 'nombre', 'modulo', 'activo'];

    public function cargos(): BelongsToMany
    {
        return $this->belongsToMany(Cargo::class, 'cargos_permisos')
            ->withTimestamps();
    }
}
