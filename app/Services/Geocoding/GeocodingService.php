<?php

namespace App\Services\Geocoding;

use App\Enums\GeocodePrecision;
use App\Models\Domain\Geography\GeocodeCache;
use App\Services\Geocoding\Contracts\GeocodingDriver;
use Illuminate\Support\Str;

class GeocodingService
{
    public function normalizeQuery(string $query): string
    {
        $query = Str::ascii(mb_strtolower(trim($query)));

        return preg_replace('/\s+/u', ' ', $query) ?? '';
    }

    public function hashQuery(string $query): string
    {
        return hash('sha256', $this->normalizeQuery($query));
    }

    public function lookupCached(string $query): ?GeocodeResult
    {
        $normalized = $this->normalizeQuery($query);
        if ($normalized === '') {
            return null;
        }

        $cache = GeocodeCache::query()
            ->where('address_hash', $this->hashQuery($normalized))
            ->first();

        if (! $cache || $cache->isExpired()) {
            return null;
        }

        $cache->increment('hit_count');

        return new GeocodeResult(
            latitude: (float) $cache->latitude,
            longitude: (float) $cache->longitude,
            precision: $cache->precisionEnum(),
            provider: $cache->provider,
            displayName: $cache->query_text,
        );
    }

    public function geocode(string $query, bool $allowProvider = true): ?GeocodeResult
    {
        if (! config('geocoding.enabled', true)) {
            return null;
        }

        $normalized = $this->normalizeQuery($query);
        if ($normalized === '') {
            return null;
        }

        $cached = $this->lookupCached($normalized);
        if ($cached !== null) {
            return $cached;
        }

        if (! $allowProvider) {
            return null;
        }

        $result = $this->driver()->geocode($normalized);
        if ($result === null) {
            return null;
        }

        $this->storeCache($normalized, $result);

        return $result;
    }

    public function storeCache(string $query, GeocodeResult $result): GeocodeCache
    {
        $normalized = $this->normalizeQuery($query);
        $ttlDays = max(1, (int) config('geocoding.cache_ttl_days', 90));

        return GeocodeCache::query()->updateOrCreate(
            ['address_hash' => $this->hashQuery($normalized)],
            [
                'query_text' => $query,
                'latitude' => $result->latitude,
                'longitude' => $result->longitude,
                'precision' => $result->precision->value,
                'provider' => $result->provider,
                'provider_payload' => $result->displayName ? ['display_name' => $result->displayName] : null,
                'expires_at' => now()->addDays($ttlDays),
            ],
        );
    }

    public function driver(): GeocodingDriver
    {
        return match (config('geocoding.driver', 'nominatim')) {
            'google' => app(GoogleGeocodingDriver::class),
            'nominatim' => app(NominatimGeocodingDriver::class),
            default => app(NominatimGeocodingDriver::class),
        };
    }

    public function buildWorksiteQuery(?string $localObra, ?string $customerAddress = null): string
    {
        $parts = array_filter([
            filled($localObra) ? trim($localObra) : null,
            filled($customerAddress) ? trim($customerAddress) : null,
            'Minas Gerais, Brasil',
        ]);

        return implode(', ', $parts);
    }
}
