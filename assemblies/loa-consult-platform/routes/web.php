<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'LOA Consult Platform',
        'status' => 'running',
    ]);
});
