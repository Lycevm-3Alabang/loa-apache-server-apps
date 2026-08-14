<?php

return [

    'secret' => env('JWT_SECRET'),

    'access_ttl' => (int) env('JWT_ACCESS_TTL', 15),

    'algo' => 'HS256',

];
