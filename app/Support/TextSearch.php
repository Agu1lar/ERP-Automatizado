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
}
