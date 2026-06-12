<?php

namespace App\Services\Geocoding;

use App\Enums\GeocodePrecision;

readonly class GeocodeResult
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public GeocodePrecision $precision,
        public string $provider,
        public ?string $displayName = null,
    ) {}

    /** @return array{lat: float, lng: float, precision: string, provider: string} */
    public function toArray(): array
    {
        return [
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'precision' => $this->precision->value,
            'provider' => $this->provider,
        ];
    }
}
