<?php

return [

    'enabled' => env('OPERATIONAL_ALERTS_ENABLED', true),

    /*
    | Permissões mínimas para cada bloco do e-mail diário.
    */
    'permissions' => [
        'overdue_returns' => 'rentals.view',
        'overdue_orders' => 'maintenance.view',
        'preventive_due' => 'maintenance.view',
    ],

    /*
    | E-mails extras (CSV) além dos usuários ativos com permissão.
    | Ex.: OPERATIONAL_ALERTS_EXTRA_RECIPIENTS=financeiro@locadora.com.br
    */
    'extra_recipients' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('OPERATIONAL_ALERTS_EXTRA_RECIPIENTS', ''))
    ))),

];
