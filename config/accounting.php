<?php

return [

    /*
    | Layouts fixos para exportação contábil (sem emissão de NF-e).
    | Omie e Sisloc: colunas esperadas em importação de contas a receber / lançamentos.
    */

    'formats' => [
        'csv' => [
            'label' => 'CSV padrão',
            'description' => 'Planilha genérica para contabilidade',
        ],
        'omie' => [
            'label' => 'Omie',
            'description' => 'Layout fixo para importação de contas a receber no Omie',
        ],
        'sisloc' => [
            'label' => 'Sisloc',
            'description' => 'Layout fixo para integração Sisloc (CAR)',
        ],
    ],

    'default_format' => env('ACCOUNTING_EXPORT_FORMAT', 'csv'),

    'omie' => [
        'categoria' => env('ACCOUNTING_OMIE_CATEGORIA', '1.01.01'),
        'conta_corrente' => env('ACCOUNTING_OMIE_CONTA_CORRENTE', '1'),
    ],

    'sisloc' => [
        'empresa_codigo' => env('ACCOUNTING_SISLOC_EMPRESA', '1'),
        'tipo_documento' => env('ACCOUNTING_SISLOC_TIPO_DOC', 'LOC'),
    ],

];
