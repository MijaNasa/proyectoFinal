<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Libro extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'isbn', 'master_id', 'serie_id', 'numero_tomo', 'portada',
        'año_edicion', 'cantidad_paginas', 'activo', 'permite_preventa',
    ];

    protected $appends = ['tiene_historial', 'portada_url'];

    public function getPortadaUrlAttribute(): string
    {
        if ($this->portada && trim((string)$this->portada) !== '' && $this->portada !== 'null') {
            if (filter_var($this->portada, FILTER_VALIDATE_URL)) {
                return $this->portada;
            }
            return asset('storage/' . ltrim($this->portada, '/'));
        }

        if ($this->relationLoaded('master') && $this->master) {
            return $this->master->portada_url;
        }

        if ($this->master_id) {
            $master = $this->master;
            if ($master) {
                return $master->portada_url;
            }
        }

        return asset('images/no-cover.png');
    }

    protected $casts = [
        'permite_preventa' => 'boolean',
        'activo' => 'boolean',
    ];

    public function setIsbnAttribute($value)
    {
        if (empty($value) || trim((string)$value) === '') {
            $this->attributes['isbn'] = null;
        } else {
            $this->attributes['isbn'] = preg_replace('/[^0-9]/', '', $value) ?: null;
        }
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(LibroMaster::class, 'master_id');
    }

    public function precios(): HasMany
    {
        return $this->hasMany(PrecioLibro::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function ventaDetalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function ordenCompraItems(): HasMany
    {
        return $this->hasMany(OrdenCompraItem::class);
    }

    public function transferencias(): HasMany
    {
        return $this->hasMany(TransferenciaStock::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoStockDetalle::class, 'libro_id');
    }

    public function getTieneHistorialAttribute(): bool
    {
        return $this->ventaDetalles()->exists() ||
               $this->ordenCompraItems()->exists() ||
               $this->transferencias()->exists() ||
               $this->movimientos()->exists();
    }

    public function precioActual()
    {
        return $this->hasOne(PrecioLibro::class)->where('activo', true)->latestOfMany('fecha_desde');
    }

    /**
     * Recalcula el precio de compra utilizando el método de Precio Promedio Ponderado (PPP)
     *
     * @param float $nuevoCosto El costo unitario de la nueva tanda ingresada
     * @param int $cantidadNueva La cantidad de unidades que ingresan
     * @return void
     */
    public function recalcularCostoPPP(float $nuevoCosto, int $cantidadNueva = 0): void
    {
        $currentPrice = $this->precioActual;
        
        // Ahora usamos el modelo de "Último Costo de Reposición".
        // El costo unitario de la nueva tanda ingresada pasa a ser directamente el costo de todo el catálogo.
        $nuevoCostoPromedio = $nuevoCosto;

        $precioVenta = $currentPrice ? $currentPrice->precio_venta : 0;

        if ($currentPrice) {
            $currentPrice->update([
                'activo' => false,
                'fecha_hasta' => now()
            ]);
        }

        $this->precios()->create([
            'precio_compra' => round($nuevoCostoPromedio, 2),
            'precio_venta'  => $precioVenta,
            'fecha_desde'   => now(),
            'activo'        => true,
        ]);
    }
}
