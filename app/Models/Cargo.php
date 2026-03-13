<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cargo extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'descripcion', 'activo'];

    public function empleados(): BelongsToMany
    {
        return $this->belongsToMany(Empleado::class, 'empleados_cargos')
            ->withPivot('fecha_desde', 'fecha_hasta')
            ->withTimestamps();
    }

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'cargos_permisos')
            ->withTimestamps();
    }
}
