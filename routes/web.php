<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'success' => true,
        'laravel_version' => app()->version(),
        'api_version' => 'v1',
    ]);
});
