<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cert User Tenant Slug
    |--------------------------------------------------------------------------
    |
    | The tenant slug used when creating cert-user accounts via the certificate
    | activation flow. Users are added to this tenant and the cert-user group.
    |
    */

    'tenant_slug' => env('CERT_USER_TENANT_SLUG', 'loa-e-cert'),

];
