<?php

namespace App\Services\Geocoding\Contracts;

use App\Services\Geocoding\GeocodeResult;

interface GeocodingDriver
{
    public function geocode(string $query): ?GeocodeResult;
}
