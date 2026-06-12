<?php

namespace App\Services\Geocoding;

use App\Enums\GeocodePrecision;
use App\Services\Geocoding\Contracts\GeocodingDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleGeocodingDriver implements GeocodingDriver
{
    public function geocode(string $query): ?GeocodeResult
    {
        $apiKey = config('geocoding.google.api_key');
        if (! filled($apiKey)) {
            Log::warning('Google geocoding skipped: GOOGLE_GEOCODING_API_KEY not set');

            return null;
        }

        $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $query,
            'key' => $apiKey,
            'region' => config('geocoding.default_country', 'br'),
            'language' => 'pt-BR',
        ]);

        if (! $response->successful()) {
            return null;
        }

        $result = $response->json('results.0');
        if (! is_array($result) || ! isset($result['geometry']['location']['lat'], $result['geometry']['location']['lng'])) {
            return null;
        }

        $location = $result['geometry']['location'];
        $locationType = (string) ($result['geometry']['location_type'] ?? 'APPROXIMATE');

        $precision = match ($locationType) {
            'ROOFTOP' => GeocodePrecision::Street,
            'RANGE_INTERPOLATED' => GeocodePrecision::Street,
            'GEOMETRIC_CENTER' => GeocodePrecision::Approximate,
            'APPROXIMATE' => GeocodePrecision::Approximate,
            default => GeocodePrecision::Approximate,
        };

        return new GeocodeResult(
            latitude: (float) $location['lat'],
            longitude: (float) $location['lng'],
            precision: $precision,
            provider: 'google',
            displayName: isset($result['formatted_address']) ? (string) $result['formatted_address'] : null,
        );
    }
}
