<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Empleado extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'legajo', 'fecha_ingreso', 
        'fecha_egreso', 'sucursal_id'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function cargos(): BelongsToMany
    {
        return $this->belongsToMany(Cargo::class, 'empleados_cargos')
            ->withPivot('fecha_desde', 'fecha_hasta')
            ->withTimestamps();
    }
}
