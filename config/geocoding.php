<?php

return [
    'enabled' => (bool) env('GEOCODING_ENABLED', true),

    'driver' => env('GEOCODING_DRIVER', 'nominatim'),

    'default_country' => env('GEOCODING_DEFAULT_COUNTRY', 'br'),

    'cache_ttl_days' => (int) env('GEOCODING_CACHE_TTL_DAYS', 90),

    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', config('app.name').' Geocoder'),
        'timeout' => (int) env('NOMINATIM_TIMEOUT', 8),
    ],

    'google' => [
        'api_key' => env('GOOGLE_GEOCODING_API_KEY'),
    ],
];
