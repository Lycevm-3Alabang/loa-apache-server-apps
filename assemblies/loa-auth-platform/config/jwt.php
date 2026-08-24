<?php

return [

    'secret' => env('JWT_SECRET'),

    'access_ttl' => (int) env('JWT_ACCESS_TTL', 15),

    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 10080),

    'algo' => 'HS256',

];
