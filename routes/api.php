<?php

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AdminActivityController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AdminDashboardController;
use App\Http\Controllers\Api\V1\AdminPatrolEntryController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BeatController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DesignationsController;
use App\Http\Controllers\Api\V1\PatrolEntryController;
use App\Http\Controllers\Api\V1\PatrollingModeController;
use App\Http\Controllers\Api\V1\PatrolTypeController;
use App\Http\Controllers\Api\V1\RangeController;
use App\Http\Controllers\Api\V1\RangeCustomFieldController;
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

    Route::get('/patrol-entries', [AdminPatrolEntryController::class, 'index']);
    Route::get('/patrol-entries/{entry}', [AdminPatrolEntryController::class, 'show']);
    Route::get('/patrol-entries/{entry}/route-points', [AdminPatrolEntryController::class, 'routePoints']);
    Route::get('/case-media/{media}', [AdminPatrolEntryController::class, 'caseMedia']);
    Route::get('/incident-media/{media}', [AdminPatrolEntryController::class, 'incidentMedia']);

    Route::get('/activities', [AdminActivityController::class, 'index']);
    Route::get('/activities/{activity}', [AdminActivityController::class, 'show']);
    Route::get('/activity-media/{media}', [AdminActivityController::class, 'media']);

    Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats']);

    Route::get('/custom-fields', [RangeCustomFieldController::class, 'index']);
    Route::post('/custom-fields', [RangeCustomFieldController::class, 'store']);
    Route::put('/custom-fields/{customField}', [RangeCustomFieldController::class, 'update']);
    Route::delete('/custom-fields/{customField}', [RangeCustomFieldController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| App API — Flutter field app only
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'throttle:api', 'app.user'])->prefix('v1/app')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('app.logout');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/my-ranges', [RangeController::class, 'myRanges']);
    Route::get('/patrol-types', [PatrolTypeController::class, 'forApp']);
    Route::get('/beats', [BeatController::class, 'forApp']);
    Route::get('/patrolling-modes', [PatrollingModeController::class, 'forApp']);
    Route::get('/custom-fields', [RangeCustomFieldController::class, 'forApp']);
    Route::get('/vehicles', [VehicleController::class, 'forApp']);

    Route::get('/patrol-entries', [PatrolEntryController::class, 'index']);
    Route::post('/patrol-entries', [PatrolEntryController::class, 'store']);
    Route::get('/patrol-entries/field-suggestions', [PatrolEntryController::class, 'fieldSuggestions']);
    Route::get('/patrol-entries/{entry}', [PatrolEntryController::class, 'show']);
    Route::patch('/patrol-entries/{entry}', [PatrolEntryController::class, 'update']);
    Route::post('/patrol-entries/{entry}/start', [PatrolEntryController::class, 'startPatrol']);
    Route::patch('/patrol-entries/{entry}/current-travel-mode', [PatrolEntryController::class, 'setCurrentTravelMode']);
    Route::post('/patrol-entries/{entry}/end', [PatrolEntryController::class, 'endPatrol']);
    Route::post('/patrol-entries/{entry}/incidents', [PatrolEntryController::class, 'addIncident']);
    Route::patch('/patrol-entries/{entry}/incidents/{incident}/status', [PatrolEntryController::class, 'updateIncidentStatus']);
    Route::post('/patrol-entries/{entry}/cases', [PatrolEntryController::class, 'addCaseReport']);
    Route::patch('/patrol-entries/{entry}/cases/{caseReport}/status', [PatrolEntryController::class, 'updateCaseReportStatus']);
    Route::post('/patrol-entries/{entry}/notes', [PatrolEntryController::class, 'addNote']);
    Route::get('/patrol-entries/{entry}/route-points', [PatrolEntryController::class, 'routePoints']);

    Route::get('/activities', [ActivityController::class, 'index']);
    Route::post('/activities', [ActivityController::class, 'store']);
    Route::get('/activities/{activity}', [ActivityController::class, 'show']);
    Route::post('/activities/{activity}/end', [ActivityController::class, 'end']);
    Route::post('/activities/{activity}/participants', [ActivityController::class, 'addParticipant']);
    Route::delete('/activities/{activity}/participants/{participant}', [ActivityController::class, 'removeParticipant']);
    Route::post('/activities/{activity}/media', [ActivityController::class, 'addMedia']);

    Route::post('/patrol-entries/{entry}/gps', [PatrolEntryController::class, 'addGpsPing']);
});
