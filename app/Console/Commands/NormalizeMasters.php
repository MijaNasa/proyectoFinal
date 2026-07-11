<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LibroMaster;
use App\Models\Libro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NormalizeMasters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:normalize-masters';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unifica los LibroMaster eliminando el numero de tomo del titulo';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando normalizacion de LibroMasters...');

        DB::transaction(function () {
            // Obtenemos todos los masters que tienen " Tomo " en su titulo
            $mastersToFix = LibroMaster::where('titulo', 'like', '% Tomo %')->get();
            $count = 0;

            foreach ($mastersToFix as $master) {
                // Extraemos el titulo real (ej: "Dragon Ball" de "Dragon Ball Tomo 1")
                $cleanTitle = preg_replace('/ Tomo \d+$/i', '', $master->titulo);

                // Buscamos si ya existe un Master unificado con ese titulo, autor y categoria
                $unifiedMaster = LibroMaster::where('titulo', $cleanTitle)
                                            ->where('autor_id', $master->autor_id)
                                            ->where('categoria_id', $master->categoria_id)
                                            ->first();

                // Si no existe, creamos uno nuevo como base
                if (!$unifiedMaster) {
                    $unifiedMaster = LibroMaster::create([
                        'titulo' => $cleanTitle,
                        'titulo_original' => preg_replace('/ Vol \d+$/i', '', $master->titulo_original ?? ''),
                        'autor_id' => $master->autor_id,
                        'categoria_id' => $master->categoria_id,
                        'activo' => true
                    ]);
                }

                // Movemos todos los libros asociados al master viejo hacia el unificado
                Libro::where('master_id', $master->id)->update(['master_id' => $unifiedMaster->id]);
                $count++;
            }

            $this->info("Se han normalizado {$count} libros y asociado a Masters unificados.");

            // Eliminamos los masters viejos que ya no tienen libros
            $deletedCount = LibroMaster::doesntHave('libros')->delete();

            $this->info("Se eliminaron {$deletedCount} LibroMasters redundantes (vacios).");
        });

        $this->info('Normalizacion completada con exito.');
    }
}
