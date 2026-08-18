<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleGeocodingService
{
    /**
     * Resolve a lat/lng pair to a human-readable address via the Google Geocoding API.
     *
     * Returns null (rather than throwing) on any failure — reverse geocoding is an
     * enrichment, and a slow/unavailable Google API must never block patrol logging.
     */
    public function reverseGeocode(float $latitude, float $longitude): ?string
    {
        $key = config('services.google_maps.key');

        if (empty($key)) {
            return null;
        }

        try {
            $response = Http::timeout(5)
                ->retry(1, 200)
                ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'latlng' => "{$latitude},{$longitude}",
                    'key' => $key,
                ]);

            if (! $response->successful()) {
                Log::warning('Google geocoding request failed.', ['status' => $response->status()]);

                return null;
            }

            $body = $response->json();

            if (($body['status'] ?? null) !== 'OK' || empty($body['results'][0]['formatted_address'])) {
                return null;
            }

            return $body['results'][0]['formatted_address'];
        } catch (\Throwable $e) {
            Log::warning('Google geocoding request threw an exception.', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
