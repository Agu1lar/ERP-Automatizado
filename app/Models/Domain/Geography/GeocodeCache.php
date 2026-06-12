<?php

namespace App\Models\Domain\Geography;

use App\Enums\GeocodePrecision;
use Illuminate\Database\Eloquent\Model;

class GeocodeCache extends Model
{
    protected $table = 'geocode_cache';

    protected $fillable = [
        'address_hash',
        'query_text',
        'latitude',
        'longitude',
        'precision',
        'provider',
        'provider_payload',
        'hit_count',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'provider_payload' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function precisionEnum(): GeocodePrecision
    {
        return GeocodePrecision::tryFrom($this->precision) ?? GeocodePrecision::Approximate;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
