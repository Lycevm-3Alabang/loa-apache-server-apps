<?php

return [
    'admin_group' => env('AUTH_ADMIN_GROUP', 'loa-auth-admin'),

    'tenant_slug' => env('TENANT_SLUG'),

    'redirect_url' => env('AUTH_REDIRECT_URL', 'https://consult.loa.edu.ph'),

    'allowed_redirects' => array_values(array_filter(array_map(
        static fn (string $url): string => trim($url),
        explode(',', env(
            'AUTH_ALLOWED_REDIRECTS',
            'https://consult.loa.edu.ph,https://cert.loa.edu.ph',
        )),
    ))),
];
