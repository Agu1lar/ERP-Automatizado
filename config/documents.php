<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dados da empresa (cabeçalho dos documentos)
    |--------------------------------------------------------------------------
    | Ajuste no .env ou substitua o Blade em resources/views/documents/
    */
    'company' => [
        'name' => env('DOCUMENTS_COMPANY_NAME', 'ACESSO equipamentos'),
        'document' => env('DOCUMENTS_COMPANY_DOCUMENT', ''),
        'address' => env('DOCUMENTS_COMPANY_ADDRESS', ''),
        'phone' => env('DOCUMENTS_COMPANY_PHONE', ''),
        'email' => env('DOCUMENTS_COMPANY_EMAIL', ''),
        'logo_path' => env('DOCUMENTS_LOGO_PATH', 'stack/assets/logo.png'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Templates Blade por tipo de documento
    |--------------------------------------------------------------------------
    | Para personalizar a OS, edite resources/views/documents/maintenance-order.blade.php
    */
    'templates' => [
        'maintenance_order' => 'documents.maintenance-order',
        'rental_summary' => 'documents.rental-summary',
        'asset_sheet' => 'documents.asset-sheet',
    ],

];
