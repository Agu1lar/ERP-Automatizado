<?php

namespace App\Services;

use App\Models\Domain\Rental\Rental;
use App\Services\Geocoding\GeocodingService;

class RentalWorksiteGeocodingService
{
    public function __construct(
        private readonly GeocodingService $geocoding,
    ) {}

    public function geocodeAndStore(Rental $rental, bool $allowProvider = true): bool
    {
        $rental->loadMissing('customer');

        if (blank($rental->local_obra)) {
            $this->clearCoordinates($rental);

            return false;
        }

        $query = $this->geocoding->buildWorksiteQuery(
            $rental->local_obra,
            $rental->customer?->endereco,
        );

        $result = $this->geocoding->geocode($query, $allowProvider);
        if ($result === null) {
            return false;
        }

        $rental->update([
            'obra_latitude' => round($result->latitude, 7),
            'obra_longitude' => round($result->longitude, 7),
            'obra_geocode_precision' => $result->precision->value,
            'obra_geocoded_at' => now(),
        ]);

        return true;
    }

    public function clearCoordinates(Rental $rental): void
    {
        if (
            $rental->obra_latitude === null
            && $rental->obra_longitude === null
            && $rental->obra_geocode_precision === null
            && $rental->obra_geocoded_at === null
        ) {
            return;
        }

        $rental->update([
            'obra_latitude' => null,
            'obra_longitude' => null,
            'obra_geocode_precision' => null,
            'obra_geocoded_at' => null,
        ]);
    }
}
