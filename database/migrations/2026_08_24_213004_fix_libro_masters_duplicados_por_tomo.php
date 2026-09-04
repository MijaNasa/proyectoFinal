<?php

use App\Models\Libro;
use App\Models\LibroMaster;
use App\Models\Suscripcion;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * RealCatalogSeeder creaba un LibroMaster nuevo por cada tomo (ej. "Jujutsu
     * Kaisen #01", "#02", ...) en vez de un solo master por serie con varios
     * tomos adentro. Esto fusiona esos duplicados en un único master.
     */
    public function up(): void
    {
        $grupos = [];

        foreach (LibroMaster::withTrashed()->get() as $master) {
            if (!preg_match('/^(.+) #(\d+)$/u', $master->titulo, $m)) {
                continue;
            }
            $grupos[$m[1]][] = ['master' => $master, 'numero' => (int) $m[2]];
        }

        foreach ($grupos as $serie => $items) {
            usort($items, fn($a, $b) => $a['numero'] <=> $b['numero']);
            $canonico = $items[0]['master'];
            $canonico->titulo = $serie;
            $canonico->save();

            if (count($items) < 2) {
                continue;
            }

            foreach (array_slice($items, 1) as $item) {
                $duplicado = $item['master'];

                Libro::where('master_id', $duplicado->id)->update(['master_id' => $canonico->id]);

                foreach (Suscripcion::where('libro_master_id', $duplicado->id)->get() as $suscripcion) {
                    $yaExiste = Suscripcion::where('cliente_id', $suscripcion->cliente_id)
                        ->where('libro_master_id', $canonico->id)
                        ->where('sucursal_id', $suscripcion->sucursal_id)
                        ->exists();

                    if ($yaExiste) {
                        $suscripcion->delete();
                    } else {
                        $suscripcion->update(['libro_master_id' => $canonico->id]);
                    }
                }

                $duplicado->delete();
            }
        }
    }

    public function down(): void
    {
        // Reparación de datos, no reversible.
    }
};