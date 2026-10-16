<?php

return [
    /*
     * Shared secret callers must present via `Authorization: Bearer <RELAY_SECRET>`.
     * Compared in constant time. No Sanctum / no user identity involved.
     */
    'secret' => env('RELAY_SECRET'),

    /*
     * Recipient domains this endpoint is allowed to deliver to. Comma-separated
     * via env RELAY_ALLOWED_DOMAINS; defaults cover the host's own domains plus
     * the public-mail domains we rely on.
     */
    'allowed_domains' => array_filter(array_map(
        fn ($domain) => trim(mb_strtolower($domain)),
        explode(',', (string) env(
            'RELAY_ALLOWED_DOMAINS',
            'lyceumalabang.edu.ph,itmlyceumalabang.onmicrosoft.com,gmail.com,outlook.com'
        ))
    )),
];
