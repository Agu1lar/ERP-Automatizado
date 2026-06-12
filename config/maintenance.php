<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Valor hora padrão da mão de obra (manutenção)
    |--------------------------------------------------------------------------
    | Usado na análise financeira para estimar custo de horas registradas em OS.
    */
    'default_hourly_rate' => (float) env('MAINTENANCE_HOURLY_RATE', 65.00),

    /*
    |--------------------------------------------------------------------------
    | Preventiva vencida — abrir OS automaticamente
    |--------------------------------------------------------------------------
    */
    'auto_open_preventive_orders' => (bool) env('MAINTENANCE_AUTO_OPEN_PREVENTIVE', true),

    /*
    |--------------------------------------------------------------------------
    | Modo da preventiva automática (job diário)
    |--------------------------------------------------------------------------
    | alert — apenas alerta (dashboard / comando), não abre OS
    | open_when_available — abre OS só com patrimônio Disponível (padrão)
    | open — alias de open_when_available (compatibilidade)
    */
    'preventive_auto_mode' => env('MAINTENANCE_PREVENTIVE_MODE', 'open_when_available'),

    /*
    | Horas antes do vencimento para exibir alerta "preventiva próxima"
    */
    'preventive_warning_hours' => (float) env('MAINTENANCE_PREVENTIVE_WARNING_HOURS', 50),

    /*
    |--------------------------------------------------------------------------
    | Baixa automática de estoque ao concluir OS
    |--------------------------------------------------------------------------
    | Peças vinculadas ao catálogo (por código) têm o saldo reduzido na conclusão.
    */
    'auto_deduct_parts_on_complete' => (bool) env('MAINTENANCE_AUTO_DEDUCT_PARTS', true),

    /*
    |--------------------------------------------------------------------------
    | Permitir estoque negativo na baixa da OS
    |--------------------------------------------------------------------------
    */
    'allow_negative_part_stock' => (bool) env('MAINTENANCE_ALLOW_NEGATIVE_STOCK', false),

];
