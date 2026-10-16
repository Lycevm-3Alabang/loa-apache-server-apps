<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'LOA Consult API',
    version: '1.0.0',
    description: 'Consultation and faculty evaluation platform'
)]
#[OA\PathItem(path: '/')]
abstract class Controller
{
    //
}
