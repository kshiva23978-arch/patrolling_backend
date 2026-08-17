<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DesignationsController;
use App\Http\Controllers\Api\V1\PatrollingModeController;
use App\Http\Controllers\Api\V1\RangeController;
use App\Http\Controllers\Api\V1\RolesController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserDetailsController;
use App\Http\Controllers\Api\V1\UserRangeAccessController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Separate logins — an admin account cannot log into the app and vice versa.
Route::middleware('throttle:20,1')->prefix('v1')->group(function () {
    Route::post('/admin/login', [AuthController::class, 'adminLogin'])->name('admin.login');
    Route::post('/app/login', [AuthController::class, 'appLogin'])->name('app.login');
});

// Shared "who am I" endpoint — available to any authenticated user regardless of type.
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('v1')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

/*
|--------------------------------------------------------------------------
| Admin API — Next.js admin panel only
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'throttle:api', 'admin'])->prefix('v1/admin')->group(function () {
    Route::apiResource('admins', AdminController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::apiResource('users', UserController::class)->only(['index', 'show', 'update','store']);
    Route::apiResource('designations', DesignationsController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::apiResource('roles', RolesController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::apiResource('user-details', UserDetailsController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::apiResource('ranges', RangeController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::apiResource('patrolling-modes', PatrollingModeController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::get('/user-range-access', [UserRangeAccessController::class, 'index']);
    Route::post('/user-range-access', [UserRangeAccessController::class, 'store']);
    Route::delete('/user-range-access/{userId}/{rangeId}', [UserRangeAccessController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| App API — Flutter field app only
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'throttle:api', 'app.user'])->prefix('v1/app')->group(function () {
    Route::get('/my-ranges', [RangeController::class, 'myRanges']);
});
