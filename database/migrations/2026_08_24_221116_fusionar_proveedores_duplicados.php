<?php

use App\Models\Proveedor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * nombre_empresa tiene un mutator que normaliza el texto al guardarlo
     * (Str::title(strtolower(...))). Los seeders comparaban con el nombre
     * original sin pasar por esa normalizacion, asi que nombres con siglas
     * o preposiciones en minuscula (ej. "ECC Ediciones", "Siglo XXI",
     * "Fondo de Cultura Economica") nunca volvian a matchear lo ya guardado
     * y se creaban de nuevo en cada corrida del seeder (ver fix en
     * RealCatalogSeeder/ProveedorSeeder del mismo dia). Esto fusiona los
     * duplicados que ya se acumularon.
     */
    public function up(): void
    {
        $grupos = Proveedor::all()->groupBy(fn ($p) => mb_strtolower($p->nombre_empresa, 'UTF-8'));

        foreach ($grupos as $items) {
            if ($items->count() < 2) {
                continue;
            }

            $ordenados = $items->sortBy('id')->values();
            $canonico = $ordenados->first();

            foreach ($ordenados->slice(1) as $dup) {
                DB::table('libro_masters')->where('proveedor_id', $dup->id)->update(['proveedor_id' => $canonico->id]);
                DB::table('ordenes_compra')->where('proveedor_id', $dup->id)->update(['proveedor_id' => $canonico->id]);
                DB::table('series')->where('proveedor_id', $dup->id)->update(['proveedor_id' => $canonico->id]);

                $dup->delete();
            }
        }
    }

    public function down(): void
    {
        // Fusion de datos, no reversible.
    }
};