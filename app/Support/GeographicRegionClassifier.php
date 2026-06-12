<?php

namespace App\Support;

use App\Enums\GeographicRegion;
use Illuminate\Support\Str;

class GeographicRegionClassifier
{
    public function classify(?string $localObra, ?string $customerAddress = null): GeographicRegion
    {
        $text = $this->normalize($localObra);

        if ($text === '') {
            $text = $this->normalize($customerAddress);
        }

        if ($text === '') {
            return GeographicRegion::Indefinido;
        }

        if ($this->matchesAny($text, config('geography.interior_keywords', []))) {
            return GeographicRegion::Interior;
        }

        if ($this->matchesBh($text)) {
            return GeographicRegion::Bh;
        }

        if ($this->matchesCityList($text, config('geography.rmbh_cities', []))) {
            return GeographicRegion::Rmbh;
        }

        if ($this->matchesCityList($text, config('geography.interior_cities', []))) {
            return GeographicRegion::Interior;
        }

        if ($this->looksLikeInteriorMg($text)) {
            return GeographicRegion::Interior;
        }

        return GeographicRegion::Indefinido;
    }

    public function classifyValue(?string $localObra, ?string $customerAddress = null): string
    {
        return $this->classify($localObra, $customerAddress)->value;
    }

    private function matchesBh(string $text): bool
    {
        if ($this->matchesAny($text, config('geography.bh_keywords', []))) {
            return true;
        }

        return (bool) preg_match('/\bbh\b/u', $text);
    }

    /** @param  list<string>  $needles */
    private function matchesAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            $normalized = $this->normalize($needle);
            if ($normalized !== '' && str_contains($text, $normalized)) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $cities */
    private function matchesCityList(string $text, array $cities): bool
    {
        foreach ($cities as $city) {
            $normalized = $this->normalize($city);
            if ($normalized === '') {
                continue;
            }

            if (str_contains($text, $normalized)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeInteriorMg(string $text): bool
    {
        if (preg_match('/\bmg\b/u', $text) || str_contains($text, 'minas gerais')) {
            return true;
        }

        return false;
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
