<?php

namespace App\Support;

use Illuminate\Support\Str;

class TextSearch
{
    public static function normalize(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Str::lower(Str::ascii(trim($value)));
    }

    public static function matches(?string $haystack, string $needle): bool
    {
        $normalizedNeedle = self::normalize($needle);

        if ($normalizedNeedle === '') {
            return false;
        }

        return str_contains(self::normalize($haystack), $normalizedNeedle);
    }

    public static function matchesAny(string $needle, ?string ...$haystacks): bool
    {
        foreach ($haystacks as $haystack) {
            if (self::matches($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** Correspondência flexível (plural/singular e prefixo), útil para categorias e tipos de equipamento. */
    public static function matchesFlexible(?string $haystack, string $needle): bool
    {
        if (self::matches($haystack, $needle)) {
            return true;
        }

        $haystackNorm = self::normalize($haystack);
        $needleNorm = self::normalize($needle);

        if ($haystackNorm === '' || $needleNorm === '') {
            return false;
        }

        $haystackSingular = rtrim($haystackNorm, 's');
        $needleSingular = rtrim($needleNorm, 's');

        return $haystackSingular === $needleSingular
            || str_starts_with($haystackNorm, $needleSingular)
            || str_starts_with($needleNorm, $haystackSingular);
    }
}
