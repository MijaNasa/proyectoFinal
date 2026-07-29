<?php

namespace App\Jobs;

use App\Models\Venta;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodeAddressJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Venta $venta
    ) {}

    public function handle(): void
    {
        if (!$this->venta->direccion_envio || $this->venta->tipo_envio !== 'domicilio') {
            return;
        }

        $address = $this->venta->direccion_envio;
        
        // Limpiar la dirección para Nominatim (remover Obs, CP, Piso, etc.)
        $cleanAddress = explode('|', $address)[0]; // Quitar observaciones
        $parts = array_map('trim', explode(',', $cleanAddress));
        
        $validParts = [];
        foreach ($parts as $part) {
            $lower = strtolower($part);
            if (!str_starts_with($lower, 'cp ') && !str_starts_with($lower, 'piso ') && !str_starts_with($lower, 'depto ')) {
                $validParts[] = $part;
            }
        }
        
        // Calle+numero, localidad y provincia (en ese orden, ver Checkout/Index.vue::confirmar())
        $searchAddress = implode(', ', array_slice($validParts, 0, 3));

        if (!str_contains(strtolower($searchAddress), 'argentina')) {
            $searchAddress .= ', Argentina';
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'MijaNasa-Logistics/1.0'
            ])->timeout(5)->get('https://nominatim.openstreetmap.org/search', [
                'q' => $searchAddress,
                'format' => 'json',
                'limit' => 1,
            ]);

            $results = $response->json();
            if (is_array($results) && count($results) > 0) {
                $location = $results[0];
                $this->venta->update([
                    'latitud'  => $location['lat'],
                    'longitud' => $location['lon'], // Nominatim usa 'lon'
                ]);
                
                // Si la venta ya está asignada a paradas, actualizar la parada también
                $this->venta->paradas()->update([
                    'latitud' => $location['lat'],
                    'longitud' => $location['lon']
                ]);
            } else {
                Log::warning("Nominatim no encontró resultados para: {$searchAddress}");
            }
        } catch (\Throwable $e) {
            Log::error("Error geocodificando Venta ID {$this->venta->id}: " . $e->getMessage());
        }
    }
}
