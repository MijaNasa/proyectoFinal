<?php

namespace App\Support;

/**
 * Reglas del costo de envio, en un solo lugar (checkout y envio de acumulados).
 *
 * - Reparto local (Gran Rosario, tipo 'domicilio'): siempre sin cargo.
 * - Correo a domicilio ('correo_nacional'): sin cargo desde $100.000 en libros.
 * - Correo a sucursal ('correo_sucursal'): sin cargo desde $80.000 en libros.
 * - Retiro / acumulacion: sin cargo.
 *
 * El monto se cuenta sobre el total de los libros (con descuentos), sin el envio.
 */
class CostoEnvio
{
    public const COSTO_CORREO = 50000;
    public const GRATIS_CORREO_DOMICILIO = 100000;
    public const GRATIS_CORREO_SUCURSAL = 80000;

    public static function calcular(?string $tipoEnvio, float $totalLibros): float
    {
        return match ($tipoEnvio) {
            'correo_nacional' => $totalLibros >= self::GRATIS_CORREO_DOMICILIO ? 0 : self::COSTO_CORREO,
            'correo_sucursal' => $totalLibros >= self::GRATIS_CORREO_SUCURSAL ? 0 : self::COSTO_CORREO,
            default           => 0,
        };
    }
}
