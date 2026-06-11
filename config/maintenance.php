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

];
