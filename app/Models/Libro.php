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
        'isbn', 'master_id', 'serie_id', 'numero_tomo',
        'año_edicion', 'cantidad_paginas', 'activo', 'permite_preventa',
    ];

    protected $casts = [
        'permite_preventa' => 'boolean',
        'activo' => 'boolean',
    ];

    public function master(): BelongsTo
    {
        return $this->belongsTo(LibroMaster::class, 'master_id');
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function precios(): HasMany
    {
        return $this->hasMany(PrecioLibro::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
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
    public function recalcularCostoPPP(float $nuevoCosto, int $cantidadNueva): void
    {
        $currentPrice = $this->precioActual;

        $stockActual = $this->stocks()->sum('cantidad_disponible');
        $costoPromedioActual = $currentPrice ? (float) $currentPrice->precio_compra : 0;

        $nuevoCostoPromedio = 0;

        if ($stockActual + $cantidadNueva > 0) {
            $nuevoCostoPromedio = (($stockActual * $costoPromedioActual) + ($cantidadNueva * $nuevoCosto)) / ($stockActual + $cantidadNueva);
        }

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
