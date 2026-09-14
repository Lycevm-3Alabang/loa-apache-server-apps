<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cert Platform Base URL
    |--------------------------------------------------------------------------
    |
    | Used by SetPasswordController::showByCert() to validate certificate
    | activation links (calls the public verify endpoint).
    |
    */

    'base_url' => env('CERT_PLATFORM_URL', ''),

];
