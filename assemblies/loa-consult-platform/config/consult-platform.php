<?php

return [
    'tenant_slug' => env('TENANT_SLUG', 'loa'),
    'refresh_cookie' => env('REFRESH_COOKIE', 'loa_connect_refresh'),
    'refresh_cookie_secure' => env('REFRESH_COOKIE_SECURE', true),
    'refresh_cookie_ttl' => (int) env('REFRESH_COOKIE_TTL', 10080),
];
