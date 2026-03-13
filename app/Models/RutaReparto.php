<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RutaReparto extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'rutas_reparto';

    protected $fillable = ['nombre', 'fecha', 'repartidor_id', 'activa'];

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'repartidor_id');
    }

    public function paradas(): HasMany
    {
        return $this->hasMany(ParadaReparto::class, 'ruta_reparto_id');
    }
}
