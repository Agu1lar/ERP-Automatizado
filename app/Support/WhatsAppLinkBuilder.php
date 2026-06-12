<?php

namespace App\Support;

class WhatsAppLinkBuilder
{
    public static function build(string $phone, string $message): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        $country = config('crm.messaging.default_country_code', '55');

        if ($digits !== '' && ! str_starts_with($digits, $country) && strlen($digits) <= 11) {
            $digits = $country.$digits;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }
}
