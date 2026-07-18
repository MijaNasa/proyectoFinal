<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Venta extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'fecha', 'cliente_id', 'user_id',
        'sucursal_id', 'tipo', 'total',
        'estado', 'estado_envio', 'tipo_envio', 'direccion_envio',
        'pago_expira_at', 'payment_id',
        'origen', 'motivo_pendiente', 'metodo_pago', 'comprobante_path'
    ];

    protected $casts = [
        'pago_expira_at' => 'datetime',
    ];

    protected $appends = ['atendido_por'];

    public function getAtendidoPorAttribute()
    {
        if ($this->user) {
            return $this->user;
        }

        if ($this->sucursal_id) {
            $adminEmpleado = Empleado::where('sucursal_id', $this->sucursal_id)
                ->whereHas('cargos', function ($q) {
                    $q->where('nombre', 'ADMIN');
                })
                ->with('user:id,name,apellido')
                ->first();
            
            if ($adminEmpleado && $adminEmpleado->user) {
                return $adminEmpleado->user;
            }
        }
        
        return null;
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function transacciones(): MorphMany
    {
        return $this->morphMany(Transaccion::class, 'transaccionable');
    }

    public function paradas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ParadaReparto::class);
    }
}
