<?php

use App\Models\Libro;
use App\Models\LibroMaster;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Hay LibroMaster de relleno (texto Lorem Ipsum, sin proveedor/editorial)
     * cargados de alguna siembra manual vieja, ajenos al catalogo real de
     * comics. Se desactivan (no se borran: varios tienen venta_detalles
     * historicos que los referencian) para que dejen de aparecer en el
     * catalogo publico y en el panel de administracion.
     */
    public function up(): void
    {
        $masters = LibroMaster::whereNull('proveedor_id')->get();

        foreach ($masters as $master) {
            $master->activo = false;
            $master->save();

            Libro::where('master_id', $master->id)->update(['activo' => false]);
        }
    }

    public function down(): void
    {
        // No revertimos: no hay forma de distinguir estos masters de una
        // desactivacion manual legitima hecha despues de esta migracion.
    }
};