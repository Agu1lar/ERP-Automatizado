<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ponte fiscal — emissão no ERP (Omie / Bling), não no Gestão Acesso
    |--------------------------------------------------------------------------
    */
    'enabled' => env('FISCAL_BRIDGE_ENABLED', true),

    'default_erp' => env('FISCAL_DEFAULT_ERP', 'omie'),

    'omie' => [
        'app_key' => env('OMIE_APP_KEY'),
        'app_secret' => env('OMIE_APP_SECRET'),
        'nfse_service_code' => env('OMIE_NFSE_SERVICE_CODE', '1.01'),
    ],
];
