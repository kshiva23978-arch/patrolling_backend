<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BeatController;
use App\Http\Controllers\Api\V1\DesignationsController;
use App\Http\Controllers\Api\V1\PatrolEntryController;
use App\Http\Controllers\Api\V1\PatrollingModeController;
use App\Http\Controllers\Api\V1\PatrolTypeController;
use App\Http\Controllers\Api\V1\RangeController;
use App\Http\Controllers\Api\V1\RolesController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserDetailsController;
use App\Http\Controllers\Api\V1\UserRangeAccessController;
use App\Http\Controllers\Api\V1\VehicleController;
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
    Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');
    Route::apiResource('admins', AdminController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::apiResource('users', UserController::class)->only(['index', 'show', 'update','store']);
    Route::apiResource('designations', DesignationsController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::apiResource('roles', RolesController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::apiResource('user-details', UserDetailsController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::apiResource('ranges', RangeController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::apiResource('patrolling-modes', PatrollingModeController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::apiResource('patrol-types', PatrolTypeController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::apiResource('beats', BeatController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::apiResource('vehicles', VehicleController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
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
    Route::get('/patrol-types', [PatrolTypeController::class, 'forApp']);
    Route::get('/beats', [BeatController::class, 'forApp']);
    Route::get('/patrolling-modes', [PatrollingModeController::class, 'forApp']);

    Route::get('/patrol-entries', [PatrolEntryController::class, 'index']);
    Route::post('/patrol-entries', [PatrolEntryController::class, 'store']);
    Route::get('/patrol-entries/{entry}', [PatrolEntryController::class, 'show']);
    Route::post('/patrol-entries/{entry}/end', [PatrolEntryController::class, 'endPatrol']);
    Route::post('/patrol-entries/{entry}/cases', [PatrolEntryController::class, 'addCaseReport']);

    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/patrol-entries/{entry}/gps', [PatrolEntryController::class, 'addGpsPing']);
    });
});
