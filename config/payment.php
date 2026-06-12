<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Driver de cobrança (PIX / boleto)
    |--------------------------------------------------------------------------
    |
    | mock — desenvolvimento e testes (URLs fictícias)
    | asaas — produção via API Asaas (https://docs.asaas.com)
    |
    */
    'driver' => env('PAYMENT_GATEWAY_DRIVER', 'mock'),

    'asaas' => [
        'api_key' => env('ASAAS_API_KEY'),
        'environment' => env('ASAAS_ENVIRONMENT', 'sandbox'),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
        'base_url' => env('ASAAS_ENVIRONMENT', 'sandbox') === 'production'
            ? 'https://api.asaas.com'
            : 'https://sandbox.asaas.com',
    ],
];
