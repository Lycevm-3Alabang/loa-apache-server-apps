<?php

return [

    'base_url' => env('AUTH_BASE_URL', 'https://auth.lyceumalabang.edu.ph'),

    'encryption_key' => env('ENCRYPTION_KEY', ''),

    'encryption_key_previous' => env('ENCRYPTION_KEY_PREVIOUS', ''),

    'http_timeout' => (int) env('AUTH_HTTP_TIMEOUT', 5),

];
