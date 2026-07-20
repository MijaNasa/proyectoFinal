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

        $apiKey = env('GOOGLE_MAPS_API_KEY') ?: env('VITE_GOOGLE_MAPS_API_KEY');
        if (!$apiKey) return;

        $address = $this->venta->direccion_envio;
        $defaultCity = env('DEFAULT_CITY', 'Rosario, Santa Fe, Argentina');
        if (!str_contains(strtolower($address), 'rosario') && !str_contains(strtolower($address), 'santa fe')) {
            $address .= ', ' . $defaultCity;
        }

        try {
            $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key'     => $apiKey,
            ]);

            $location = $response->json('results.0.geometry.location');
            if ($location) {
                $this->venta->update([
                    'latitud'  => $location['lat'],
                    'longitud' => $location['lng'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("Error geocodificando Venta ID {$this->venta->id}: " . $e->getMessage());
        }
    }
}
