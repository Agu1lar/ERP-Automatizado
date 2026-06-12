<?php

namespace App\Support;

use App\Enums\GeocodePrecision;
use App\Enums\GeographicRegion;
use App\Models\Domain\Rental\Rental;
use App\Services\Geocoding\GeocodingService;
use Illuminate\Support\Str;

class WorksiteMapLocator
{
    public function __construct(
        private readonly GeocodingService $geocoding,
    ) {}

    /** @return array{lat: float, lng: float, precision: string, precision_label: string, city: ?string} */
    public function locate(Rental $rental): array
    {
        $rental->loadMissing('customer');

        if ($rental->obra_latitude !== null && $rental->obra_longitude !== null) {
            $precision = GeocodePrecision::tryFrom((string) $rental->obra_geocode_precision)
                ?? GeocodePrecision::Street;

            return $this->finalize(
                (float) $rental->obra_latitude,
                (float) $rental->obra_longitude,
                $precision,
                $this->extractCity($rental->local_obra),
                applyOffset: ! $precision->isHighAccuracy(),
                rentalId: $rental->id,
            );
        }

        $query = $this->geocoding->buildWorksiteQuery(
            $rental->local_obra,
            $rental->customer?->endereco,
        );

        if ($query !== '') {
            $cached = $this->geocoding->lookupCached($query);
            if ($cached !== null) {
                return $this->finalize(
                    $cached->latitude,
                    $cached->longitude,
                    $cached->precision,
                    $this->extractCity($rental->local_obra),
                    applyOffset: ! $cached->precision->isHighAccuracy(),
                    rentalId: $rental->id,
                );
            }
        }

        return $this->locateByCityOrRegion($rental);
    }

    /** @return array{lat: float, lng: float} */
    public function defaultMapCenter(): array
    {
        $center = config('geography.map_default_center');

        return [
            'lat' => (float) $center['lat'],
            'lng' => (float) $center['lng'],
        ];
    }

    public function defaultZoom(): int
    {
        return (int) config('geography.map_default_zoom', 10);
    }

    /** @return array{lat: float, lng: float, precision: string, precision_label: string, city: ?string} */
    private function locateByCityOrRegion(Rental $rental): array
    {
        $region = $rental->regionEnum();
        $localObra = $rental->local_obra;
        $city = $this->extractCity($localObra);

        if ($city !== null) {
            $coords = config("geography.city_coordinates.{$city}");
            if (is_array($coords) && isset($coords['lat'], $coords['lng'])) {
                return $this->finalize(
                    (float) $coords['lat'],
                    (float) $coords['lng'],
                    GeocodePrecision::City,
                    $city,
                    applyOffset: true,
                    rentalId: $rental->id,
                );
            }
        }

        $center = config("geography.region_centers.{$region->value}", config('geography.region_centers.indefinido'));

        return $this->finalize(
            (float) $center['lat'],
            (float) $center['lng'],
            GeocodePrecision::Region,
            $city,
            applyOffset: true,
            rentalId: $rental->id,
        );
    }

  /** @return array{lat: float, lng: float, precision: string, precision_label: string, city: ?string} */
    private function finalize(
        float $lat,
        float $lng,
        GeocodePrecision $precision,
        ?string $city,
        bool $applyOffset,
        int $rentalId,
    ): array {
        if ($applyOffset) {
            [$lat, $lng] = $this->offsetCoordinates($lat, $lng, $rentalId);
        }

        return [
            'lat' => round($lat, 6),
            'lng' => round($lng, 6),
            'precision' => $precision->value,
            'precision_label' => $precision->label(),
            'city' => $city,
        ];
    }

    /** @return array{0: float, 1: float} */
    private function offsetCoordinates(float $lat, float $lng, int $rentalId): array
    {
        $angle = fmod($rentalId * 137.508, 360.0);
        $radiusKm = 0.4 + ($rentalId % 7) * 0.15;
        $latRad = deg2rad($lat);
        $lat += ($radiusKm / 111.0) * cos(deg2rad($angle));
        $lng += ($radiusKm / (111.0 * max(cos($latRad), 0.2))) * sin(deg2rad($angle));

        return [$lat, $lng];
    }

    private function extractCity(?string $localObra): ?string
    {
        $text = $this->normalize($localObra);
        if ($text === '') {
            return null;
        }

        $candidates = $this->cityKeys();
        usort($candidates, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($candidates as $cityKey) {
            if (str_contains($text, $cityKey)) {
                return $cityKey;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function cityKeys(): array
    {
        $fromConfig = array_keys(config('geography.city_coordinates', []));
        $fromLists = array_merge(
            config('geography.rmbh_cities', []),
            config('geography.interior_cities', []),
            ['belo horizonte'],
        );

        $keys = [];
        foreach (array_merge($fromConfig, $fromLists) as $name) {
            $normalized = $this->normalize($name);
            if ($normalized !== '') {
                $keys[$normalized] = $normalized;
            }
        }

        return array_values($keys);
    }

    private function normalize(?string $value): string
    {
        if (blank($value)) {
            return '';
        }

        $value = Str::ascii(mb_strtolower(trim($value)));

        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }
}
