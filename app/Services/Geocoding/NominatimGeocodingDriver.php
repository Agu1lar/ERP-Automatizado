<?php

namespace App\Services\Geocoding;

use App\Enums\GeocodePrecision;
use App\Services\Geocoding\Contracts\GeocodingDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NominatimGeocodingDriver implements GeocodingDriver
{
    public function geocode(string $query): ?GeocodeResult
    {
        $userAgent = config('geocoding.nominatim.user_agent');
        if (! filled($userAgent)) {
            Log::warning('Nominatim geocoding skipped: NOMINATIM_USER_AGENT not set');

            return null;
        }

        $baseUrl = rtrim((string) config('geocoding.nominatim.base_url'), '/');
        $country = config('geocoding.default_country', 'br');

        $response = Http::withHeaders([
            'User-Agent' => $userAgent,
            'Accept-Language' => 'pt-BR,pt;q=0.9',
        ])
            ->timeout((int) config('geocoding.nominatim.timeout', 8))
            ->get("{$baseUrl}/search", [
                'q' => $query,
                'format' => 'json',
                'limit' => 1,
                'countrycodes' => $country,
                'addressdetails' => 1,
            ]);

        if (! $response->successful()) {
            Log::warning('Nominatim geocoding failed', ['status' => $response->status()]);

            return null;
        }

        $row = $response->json('0');
        if (! is_array($row) || ! isset($row['lat'], $row['lon'])) {
            return null;
        }

        return new GeocodeResult(
            latitude: (float) $row['lat'],
            longitude: (float) $row['lon'],
            precision: $this->mapPrecision($row),
            provider: 'nominatim',
            displayName: isset($row['display_name']) ? (string) $row['display_name'] : null,
        );
    }

    /** @param  array<string, mixed>  $row */
    private function mapPrecision(array $row): GeocodePrecision
    {
        $type = mb_strtolower((string) ($row['type'] ?? ''));
        $class = mb_strtolower((string) ($row['class'] ?? ''));

        if (in_array($type, ['house', 'building', 'residential', 'apartments', 'terrace', 'road', 'pedestrian', 'footway'], true)) {
            return GeocodePrecision::Street;
        }

        if (in_array($class, ['highway', 'place'], true) && in_array($type, ['residential', 'suburb', 'neighbourhood', 'quarter'], true)) {
            return GeocodePrecision::Approximate;
        }

        if (in_array($type, ['city', 'town', 'village', 'municipality', 'administrative'], true)) {
            return GeocodePrecision::City;
        }

        return GeocodePrecision::Approximate;
    }
}
