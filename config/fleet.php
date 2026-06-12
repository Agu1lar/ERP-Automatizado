<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Depreciação patrimonial (linear)
    |--------------------------------------------------------------------------
    */
    'depreciation' => [
        'useful_life_years' => (int) env('FLEET_DEPRECIATION_YEARS', 10),
        'residual_percent' => (float) env('FLEET_DEPRECIATION_RESIDUAL_PERCENT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sugestão de desinvestimento (sucata / venda)
    |--------------------------------------------------------------------------
    */
    'divestment' => [
        'min_occupancy_percent' => (float) env('FLEET_DIVEST_MIN_OCCUPANCY', 15),
        'min_operating_margin_percent' => (float) env('FLEET_DIVEST_MIN_MARGIN', 0),
        'max_payback_months' => (int) env('FLEET_DIVEST_MAX_PAYBACK_MONTHS', 60),
    ],
];
