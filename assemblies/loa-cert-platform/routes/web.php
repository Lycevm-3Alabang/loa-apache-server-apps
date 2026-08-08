<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'LOA Cert Platform',
        'status' => 'running',
    ]);
});