<?php

namespace App\Support;

class CopilotMessageFormatter
{
    public static function format(string $text): string
    {
        $escaped = e($text);
        $withBold = preg_replace(
            '/\*\*(.+?)\*\*/',
            '<strong class="font-semibold">$1</strong>',
            $escaped,
        );

        return nl2br($withBold ?? $escaped, false);
    }
}
