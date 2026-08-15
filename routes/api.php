<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:20,1')->prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
});

Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('v1')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('users', \App\Http\Controllers\Api\V1\UserController::class)->only(['index', 'show']);
    Route::apiResource('designations', \App\Http\Controllers\Api\V1\DesignationsController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::apiResource('roles', \App\Http\Controllers\Api\V1\RolesController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::apiResource('user-details', \App\Http\Controllers\Api\V1\UserDetailsController::class)->only(['index', 'show', 'store', 'update', 'destroy']);


});
