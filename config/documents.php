<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dados da empresa (cabeçalho dos documentos)
    |--------------------------------------------------------------------------
    | Ajuste no .env ou substitua o Blade em resources/views/documents/
    */
    'company' => [
        'name' => env('DOCUMENTS_COMPANY_NAME', env('APP_NAME', 'Gestão Acesso')),
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
        'rental_contract' => 'documents.rental-contract',
        'asset_sheet' => 'documents.asset-sheet',
        'billing_invoice' => 'documents.billing-invoice',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cláusulas do contrato de locação (editáveis)
    |--------------------------------------------------------------------------
    */
    'rental_contract_clauses' => [
        'O LOCATÁRIO declara ter recebido o equipamento em condições de uso e se responsabiliza pela guarda, conservação e devolução no prazo acordado.',
        'O valor da locação, período e forma de pagamento constam neste contrato. Atrasos na devolução poderão gerar cobrança adicional conforme tabela de preços vigente.',
        'Danos, perdas, furtos ou extravios do equipamento são de responsabilidade do LOCATÁRIO, incluindo custos de reparo ou reposição.',
        'O LOCATÁRIO obriga-se a utilizar o equipamento conforme manual do fabricante e legislação aplicável, sendo vedado o uso por terceiros não autorizados.',
        'A rescisão antecipada não isenta o LOCATÁRIO do pagamento do período mínimo acordado, salvo negociação expressa por escrito.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Formato de impressão por documento
    |--------------------------------------------------------------------------
    | OS: largura A4 (210 mm) × 1/3 da altura A4 (99 mm) — talão para corte
    | em folha A4 (três vias na vertical). Demais documentos: A4 inteiro.
    */
    'paper' => [
        'maintenance_order' => [
            'width_mm' => 210,
            'height_mm' => 99,
        ],
        'rental_summary' => [
            'format' => 'a4',
            'orientation' => 'portrait',
        ],
        'rental_contract' => [
            'format' => 'a4',
            'orientation' => 'portrait',
        ],
        'asset_sheet' => [
            'format' => 'a4',
            'orientation' => 'portrait',
        ],
        'billing_invoice' => [
            'format' => 'a4',
            'orientation' => 'portrait',
        ],
    ],

];
