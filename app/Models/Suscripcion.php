<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Suscripcion extends Model
{
    protected $fillable = [
        'cliente_id',
        'libro_master_id',
        'sucursal_id',
        'tomo_inicio',
        'estado'
    ];

    protected $casts = [
        'tomo_inicio' => 'integer',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function serie()
    {
        return $this->belongsTo(LibroMaster::class, 'libro_master_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }
}
