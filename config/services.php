<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Mapas (Leaflet). O padrão usa os tiles públicos do OpenStreetMap, gratuitos
    | e sem chave. Para uso intenso, troque por outro provedor de tiles no .env.
    */
    /*
    | Distância pelas estradas (OpenRouteService, gratuito até 2.000 rotas/dia).
    | Sem chave, o sistema usa a linha reta × fator de estrada (Parâmetros).
    */
    'openrouteservice' => [
        'key' => env('ORS_API_KEY'),
        'url' => env('ORS_URL', 'https://api.openrouteservice.org'),
        'timeout' => (int) env('ORS_TIMEOUT', 6),
    ],

    /*
    | C2 Prev: API de Monitoramento (somente leitura). Traz o resultado dos
    | processos no INSS. Sem chave, a integração fica desligada.
    */
    'c2' => [
        'key' => env('C2_API_KEY'),
        'url' => env('C2_API_URL', 'https://monitoramento.c2prev.com'),
        'timeout' => (int) env('C2_TIMEOUT', 15),
    ],

    'map' => [
        'tiles' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env('MAP_TILE_ATTRIBUTION', '&copy; colaboradores do OpenStreetMap'),
    ],

];
