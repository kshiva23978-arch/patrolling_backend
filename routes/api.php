<?php

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AdminActivityController;
use App\Http\Controllers\Api\V1\AdminCaseEntryController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AdminDashboardController;
use App\Http\Controllers\Api\V1\AdminPatrolEntryController;
use App\Http\Controllers\Api\V1\AdminRangeAccessController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BeatController;
use App\Http\Controllers\Api\V1\CaseEntryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DesignationsController;
use App\Http\Controllers\Api\V1\PatrolEntryController;
use App\Http\Controllers\Api\V1\PatrollingModeController;
use App\Http\Controllers\Api\V1\PatrolTypeController;
use App\Http\Controllers\Api\V1\RangeController;
use App\Http\Controllers\Api\V1\RangeCustomFieldController;
use App\Http\Controllers\Api\V1\RolesController;
use App\Http\Controllers\Api\V1\StaffController;
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
    Route::apiResource('admins', AdminController::class)->only(['index', 'show', 'store', 'update', 'destroy'])
        ->middleware('admin.permission:admins');
    Route::apiResource('users', UserController::class)->only(['index', 'show', 'update','store'])
        ->middleware('admin.permission:users');
    Route::apiResource('designations', DesignationsController::class)->only(['index', 'show', 'store', 'update', 'destroy'])
        ->middleware('admin.permission:designations');
    Route::apiResource('roles', RolesController::class)->only(['index', 'show', 'store', 'update', 'destroy'])
        ->middleware('admin.permission:roles');
    Route::apiResource('user-details', UserDetailsController::class)->only(['index', 'show', 'store', 'update', 'destroy'])
        ->middleware('admin.permission:user_details');
    Route::apiResource('ranges', RangeController::class)->only(['index', 'show', 'store', 'update', 'destroy'])
        ->middleware('admin.permission:ranges');
    Route::apiResource('patrolling-modes', PatrollingModeController::class)->only(['index', 'show', 'store', 'update', 'destroy'])
        ->middleware('admin.permission:patrolling_modes');
    Route::apiResource('patrol-types', PatrolTypeController::class)->only(['index', 'show', 'store', 'update', 'destroy'])
        ->middleware('admin.permission:patrol_types');
    Route::apiResource('beats', BeatController::class)->only(['index', 'show', 'store', 'update', 'destroy'])
        ->middleware('admin.permission:beats');
    Route::apiResource('vehicles', VehicleController::class)->only(['index', 'show', 'store', 'update', 'destroy'])
        ->middleware('admin.permission:vehicles');
    Route::apiResource('staff', StaffController::class)->only(['index', 'show', 'store', 'update', 'destroy'])
        ->middleware('admin.permission:staff');

    Route::middleware('admin.permission:users')->group(function () {
        Route::get('/user-range-access', [UserRangeAccessController::class, 'index']);
        Route::post('/user-range-access', [UserRangeAccessController::class, 'store']);
        Route::delete('/user-range-access/{userId}/{rangeId}', [UserRangeAccessController::class, 'destroy']);
    });

    // Which ranges a Department Admin/Ranger admin account is scoped to —
    // gated the same as the `admins` resource itself (only whoever can
    // manage admin accounts can reassign what they're scoped to).
    Route::middleware('admin.permission:admins')->group(function () {
        Route::get('/admin-range-access', [AdminRangeAccessController::class, 'index']);
        Route::post('/admin-range-access', [AdminRangeAccessController::class, 'store']);
        Route::delete('/admin-range-access/{adminId}/{rangeId}', [AdminRangeAccessController::class, 'destroy']);
    });

    Route::middleware('admin.permission:patrollings')->group(function () {
        Route::get('/patrol-entries', [AdminPatrolEntryController::class, 'index']);
        Route::get('/patrol-entries/{entry}', [AdminPatrolEntryController::class, 'show']);
        Route::get('/patrol-entries/{entry}/route-points', [AdminPatrolEntryController::class, 'routePoints']);
        Route::get('/case-media/{media}', [AdminPatrolEntryController::class, 'caseMedia']);
        Route::get('/incident-media/{media}', [AdminPatrolEntryController::class, 'incidentMedia']);
    });

    Route::middleware('admin.permission:cases')->group(function () {
        Route::get('/case-entries', [AdminCaseEntryController::class, 'index']);
        Route::get('/case-entries/{case}', [AdminCaseEntryController::class, 'show']);
        Route::get('/case-entries/{case}/route-points', [AdminCaseEntryController::class, 'routePoints']);
        Route::get('/case-incident-media/{media}', [AdminCaseEntryController::class, 'incidentMedia']);
        Route::get('/case-filing-media/{media}', [AdminCaseEntryController::class, 'filingMedia']);
        Route::get('/case-closing-media/{media}', [AdminCaseEntryController::class, 'closingMedia']);
    });

    Route::middleware('admin.permission:activities')->group(function () {
        Route::get('/activities', [AdminActivityController::class, 'index']);
        Route::get('/activities/{activity}', [AdminActivityController::class, 'show']);
        Route::get('/activity-media/{media}', [AdminActivityController::class, 'media']);
    });

    Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats'])
        ->middleware('admin.permission:dashboard');

    Route::middleware('admin.permission:custom_fields')->group(function () {
        Route::get('/custom-fields', [RangeCustomFieldController::class, 'index']);
        Route::post('/custom-fields', [RangeCustomFieldController::class, 'store']);
        Route::put('/custom-fields/{customField}', [RangeCustomFieldController::class, 'update']);
        Route::delete('/custom-fields/{customField}', [RangeCustomFieldController::class, 'destroy']);
    });
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
    Route::get('/staff', [StaffController::class, 'forApp']);

    Route::get('/patrol-entries', [PatrolEntryController::class, 'index']);
    Route::post('/patrol-entries', [PatrolEntryController::class, 'store']);
    Route::get('/patrol-entries/field-suggestions', [PatrolEntryController::class, 'fieldSuggestions']);
    Route::get('/patrol-entries/{entry}', [PatrolEntryController::class, 'show']);
    Route::patch('/patrol-entries/{entry}', [PatrolEntryController::class, 'update']);
    Route::delete('/patrol-entries/{entry}', [PatrolEntryController::class, 'destroy']);
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

    Route::get('/cases', [CaseEntryController::class, 'index']);
    Route::post('/cases', [CaseEntryController::class, 'store']);
    Route::get('/cases/field-suggestions', [CaseEntryController::class, 'fieldSuggestions']);
    Route::get('/cases/{case}', [CaseEntryController::class, 'show']);
    Route::patch('/cases/{case}', [CaseEntryController::class, 'update']);
    Route::delete('/cases/{case}', [CaseEntryController::class, 'destroy']);
    Route::post('/cases/{case}/start', [CaseEntryController::class, 'startCase']);
    Route::patch('/cases/{case}/current-travel-mode', [CaseEntryController::class, 'setCurrentTravelMode']);
    Route::post('/cases/{case}/close', [CaseEntryController::class, 'closeCase']);
    Route::post('/cases/{case}/incidents', [CaseEntryController::class, 'addIncident']);
    Route::patch('/cases/{case}/incidents/{incident}/status', [CaseEntryController::class, 'updateIncidentStatus']);
    Route::post('/cases/{case}/filings', [CaseEntryController::class, 'addFiling']);
    Route::patch('/cases/{case}/filings/{filing}/status', [CaseEntryController::class, 'updateFilingStatus']);
    Route::post('/cases/{case}/notes', [CaseEntryController::class, 'addNote']);
    Route::get('/cases/{case}/route-points', [CaseEntryController::class, 'routePoints']);
    Route::post('/cases/{case}/gps', [CaseEntryController::class, 'addGpsPing']);
});
