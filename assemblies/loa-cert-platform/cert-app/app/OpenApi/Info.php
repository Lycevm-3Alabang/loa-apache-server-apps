<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'LOA Cert Platform API',
    version: '1.0.0',
    description: 'Certificate management API for LOA system',
    contact: new OA>Contact(name: 'LOA Dev Team', email: 'dev@lyceumalabang.edu.ph'),
    license: new OA\License(name: 'MIT')
)]
