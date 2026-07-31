<?php

$configuredOrigins = (string) env('CORS_ALLOWED_ORIGINS', '');
$defaultOrigins = [
    'https://consult.loa.edu.ph',
    'https://cert.loa.edu.ph',
    'https://auth.loa.edu.ph',
];

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The auth platform is a pure JSON API consumed by the LOA subdomain
    | applications. Only these origins may call it from a browser.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $configuredOrigins !== ''
        ? array_values(array_filter(array_map('trim', explode(',', $configuredOrigins))))
        : $defaultOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Accept',
        'Authorization',
        'X-Requested-With',
    ],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
