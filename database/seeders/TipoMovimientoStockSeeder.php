<?php

namespace Database\Seeders;

use App\Models\TipoMovimientoStock;
use Illuminate\Database\Seeder;

class TipoMovimientoStockSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['codigo' => 'INGRESO_MANUAL', 'nombre' => 'Ingreso Manual',         'descripcion' => 'Carga manual de unidades al stock'],
            ['codigo' => 'EGRESO_DANO',    'nombre' => 'Daño / Deterioro',       'descripcion' => 'Unidades dañadas o deterioradas'],
            ['codigo' => 'EGRESO_ROBO',    'nombre' => 'Robo / Extravío',        'descripcion' => 'Unidades robadas o perdidas'],
            ['codigo' => 'EGRESO_INV',     'nombre' => 'Ajuste de Inventario',   'descripcion' => 'Corrección por conteo físico'],
            ['codigo' => 'EGRESO_DEV',     'nombre' => 'Devolución a Proveedor', 'descripcion' => 'Unidades devueltas al proveedor'],
            ['codigo' => 'EGRESO_OTRO',    'nombre' => 'Otro',                   'descripcion' => 'Otro motivo de egreso'],
        ];

        foreach ($tipos as $tipo) {
            TipoMovimientoStock::firstOrCreate(
                ['codigo' => $tipo['codigo']],
                array_merge($tipo, ['afecta_stock' => true, 'activo' => true])
            );
        }
    }
}
