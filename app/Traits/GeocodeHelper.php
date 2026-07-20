<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait GeocodeHelper
{
    /**
     * Calcula la distancia usando Haversine en kilometros.
     */
    protected function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        if ($dist > 1) {
            $dist = 1;
        }
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        return $miles * 1.609344;
    }

    /**
     * Obtiene coordenadas desde Nominatim.
     */
    protected function geocodeAddress($address)
    {
        $cleanAddress = explode('|', $address)[0];
        $parts = array_map('trim', explode(',', $cleanAddress));
        
        $validParts = [];
        foreach ($parts as $part) {
            $lower = strtolower($part);
            if (!str_starts_with($lower, 'cp ') && !str_starts_with($lower, 'piso ') && !str_starts_with($lower, 'depto ')) {
                $validParts[] = $part;
            }
        }
        
        $searchAddress = implode(', ', array_slice($validParts, 0, 2));

        $defaultCity = env('DEFAULT_CITY', 'Rosario, Argentina');
        if (!str_contains(strtolower($searchAddress), 'rosario') && !str_contains(strtolower($searchAddress), 'funes') && !str_contains(strtolower($searchAddress), 'perez')) {
            $searchAddress .= ', ' . $defaultCity;
        } else if (!str_contains(strtolower($searchAddress), 'argentina')) {
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
                return [
                    'lat' => $results[0]['lat'],
                    'lon' => $results[0]['lon'],
                ];
            }
        } catch (\Throwable $e) {
            Log::error("Error geocodificando: " . $e->getMessage());
        }

        return null;
    }
}
