<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Formato extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'formatos';

    protected $fillable = ['nombre', 'activo'];

    public function libroMasters()
    {
        return $this->hasMany(LibroMaster::class, 'formato', 'nombre');
    }
}
