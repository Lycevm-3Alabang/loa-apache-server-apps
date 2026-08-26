<?php

return [
    'admin_group' => env('AUTH_ADMIN_GROUP', 'loa-auth-admin'),

    'tenant_slug' => env('TENANT_SLUG'),

    'redirect_url' => env('AUTH_REDIRECT_URL', 'https://aces-api.lyceumalabang.edu.ph'),

    // Redirect allowlist note (unified-auth-flow.md §0 D7): tenant rows are
    // the only accepted redirect origins; the former AUTH_ALLOWED_REDIRECTS
    // bootstrap list was retired in P3.

    'encryption_key' => env('ENCRYPTION_KEY', ''),
    'encryption_key_previous' => env('ENCRYPTION_KEY_PREVIOUS', ''),
];
