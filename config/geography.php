<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Classificação geográfica — locações (local da obra)
    |--------------------------------------------------------------------------
    |
    | Usado para filtro BH / RMBH / Interior em lista do dia e relatórios.
    | A classificação analisa o texto de local_obra (e endereço do cliente).
    |
    */
    'bh_keywords' => [
        'belo horizonte',
        'b.h.',
        ' bh ',
        'bh-',
        'bh,',
        'regiao metropolitana de belo horizonte',
    ],

    'rmbh_cities' => [
        'contagem',
        'betim',
        'nova lima',
        'ribeirao das neves',
        'ribeirão das neves',
        'santa luzia',
        'santa lúzia',
        'ibirite',
        'ibirité',
        'sabara',
        'sabará',
        'vespasiano',
        'matozinhos',
        'sarzedo',
        'brumadinho',
        'raposos',
        'rio acima',
        'caete',
        'caeté',
        'confins',
        'lagoa santa',
        'pedro leopoldo',
        'ribeirao das neves',
        'nova união',
        'nova uniao',
        'rio manso',
        'juatuba',
        'esmeraldas',
        'florestal',
        'igarape',
        'igarapé',
        'mario campos',
        'mário campos',
        'sao jose da lapa',
        'são josé da lapa',
        'taquaril',
    ],

    'interior_keywords' => [
        'interior',
        'interior de minas',
        'interior mg',
    ],

    'interior_cities' => [
        'uberlandia',
        'uberlândia',
        'uberaba',
        'juiz de fora',
        'montes claros',
        'divinopolis',
        'divinópolis',
        'ipatinga',
        'governador valadares',
        'sete lagoas',
        'patos de minas',
        'pouso alegre',
        'teofilo otoni',
        'teófilo otoni',
        'barbacena',
        'sabara',
        'varginha',
        'itajuba',
        'itajubá',
        'passos',
        'araxa',
        'araçuaí',
        'aracuai',
        'lavras',
        'muriae',
        'muriaé',
        'timoteo',
        'timóteo',
        'coronel fabriciano',
        'ouro preto',
        'mariana',
        'conselheiro lafaiete',
        'itabira',
        'curvelo',
        'paracatu',
        'unai',
        'unaí',
        'januaria',
        'januária',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapa de obras ativas — centros e coordenadas aproximadas
    |--------------------------------------------------------------------------
    */
    'map_default_center' => [
        'lat' => -19.9167,
        'lng' => -43.9345,
    ],

    'map_default_zoom' => 10,

    'region_centers' => [
        'bh' => ['lat' => -19.9167, 'lng' => -43.9345],
        'rmbh' => ['lat' => -19.9317, 'lng' => -44.0539],
        'interior' => ['lat' => -18.9186, 'lng' => -43.4839],
        'indefinido' => ['lat' => -19.9167, 'lng' => -43.9345],
    ],

    'city_coordinates' => [
        'belo horizonte' => ['lat' => -19.9167, 'lng' => -43.9345],
        'contagem' => ['lat' => -19.9320, 'lng' => -44.0539],
        'betim' => ['lat' => -19.9678, 'lng' => -44.1984],
        'nova lima' => ['lat' => -19.9856, 'lng' => -43.8467],
        'ribeirao das neves' => ['lat' => -19.7669, 'lng' => -44.0869],
        'ribeirão das neves' => ['lat' => -19.7669, 'lng' => -44.0869],
        'santa luzia' => ['lat' => -19.7700, 'lng' => -43.8514],
        'santa lúzia' => ['lat' => -19.7700, 'lng' => -43.8514],
        'ibirite' => ['lat' => -20.0219, 'lng' => -44.0589],
        'ibirité' => ['lat' => -20.0219, 'lng' => -44.0589],
        'sabara' => ['lat' => -19.8847, 'lng' => -43.8067],
        'sabará' => ['lat' => -19.8847, 'lng' => -43.8067],
        'vespasiano' => ['lat' => -19.6919, 'lng' => -43.9233],
        'sete lagoas' => ['lat' => -19.4658, 'lng' => -44.2467],
        'uberlandia' => ['lat' => -18.9186, 'lng' => -48.2772],
        'uberlândia' => ['lat' => -18.9186, 'lng' => -48.2772],
        'uberaba' => ['lat' => -19.7487, 'lng' => -47.9297],
        'juiz de fora' => ['lat' => -21.7642, 'lng' => -43.3503],
        'montes claros' => ['lat' => -16.7350, 'lng' => -43.8617],
        'divinopolis' => ['lat' => -20.1436, 'lng' => -44.8906],
        'divinópolis' => ['lat' => -20.1436, 'lng' => -44.8906],
        'ipatinga' => ['lat' => -19.4703, 'lng' => -42.5367],
        'governador valadares' => ['lat' => -18.8545, 'lng' => -41.9494],
    ],
];
