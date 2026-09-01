<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Read-only admin view of every admin-panel and app login attempt — see `AuthController::recordLogin`. */
class LoginLogController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'type' => ['sometimes', Rule::in([LoginLog::TYPE_ADMIN, LoginLog::TYPE_USER])],
            'successful' => ['sometimes', 'boolean'],
            'employee_id' => ['sometimes', 'string'],
        ]);

        $logs = LoginLog::query()
            ->when(
                $validated['type'] ?? null,
                fn ($query, $type) => $query->where('ll_account_type', $type)
            )
            ->when(
                array_key_exists('successful', $validated),
                fn ($query) => $query->where('ll_successful', $validated['successful'])
            )
            ->when(
                $validated['employee_id'] ?? null,
                fn ($query, $employeeId) => $query->where('ll_employee_id', 'like', "%{$employeeId}%")
            )
            ->latest('ll_created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Login logs retrieved successfully.',
            'data' => $logs->through(fn (LoginLog $log) => [
                'id' => $log->ll_id,
                'account_type' => $log->ll_account_type,
                'account_id' => $log->ll_account_id,
                'employee_id' => $log->ll_employee_id,
                'successful' => $log->ll_successful,
                'ip_address' => $log->ll_ip_address,
                'user_agent' => $log->ll_user_agent,
                'created_at' => $log->ll_created_at,
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }
}
