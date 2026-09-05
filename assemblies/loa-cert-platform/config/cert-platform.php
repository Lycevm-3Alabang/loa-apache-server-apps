<?php

return [
    'tenant_slug' => env('CERT_TENANT_SLUG', 'loa-e-cert'),
    'organization_id' => env('CERT_ORGANIZATION_ID', '00000000-0000-0000-0000-000000000001'),
    'refresh_cookie' => env('CERT_REFRESH_COOKIE', 'loa_cert_refresh'),
    'refresh_cookie_secure' => env('CERT_REFRESH_COOKIE_SECURE', true),
    'refresh_cookie_ttl' => (int) env('CERT_REFRESH_COOKIE_TTL', 10080),
    'log_viewer_secret' => env('LOG_VIEWER_SECRET', ''),
];
