<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResource;
use App\Http\Resources\RangeResource;
use App\Models\Admin;
use App\Models\Ranges;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Which ranges a Department Admin/Ranger admin account is scoped to — the
 * admin-table equivalent of {@see UserRangeAccessController}. Only makes
 * sense for a `department_admin`/`ranger`-level admin; assigning ranges to
 * a `master_admin`-level admin is harmless but a no-op (it's unrestricted
 * regardless — see `Admin::accessibleRangeIds`).
 */
class AdminRangeAccessController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'admin_id' => ['sometimes', 'string', 'uuid', 'exists:admins,a_id'],
            'range_id' => ['sometimes', 'string', 'uuid', 'exists:ranges,rn_id'],
        ]);

        if (isset($validated['admin_id'])) {
            $admin = Admin::findOrFail($validated['admin_id']);

            return response()->json([
                'success' => true,
                'message' => 'Ranges for admin retrieved successfully.',
                'data' => RangeResource::collection($admin->ranges()->with('patrollingModes')->get()),
            ]);
        }

        if (isset($validated['range_id'])) {
            $range = Ranges::findOrFail($validated['range_id']);

            return response()->json([
                'success' => true,
                'message' => 'Admins for range retrieved successfully.',
                'data' => AdminResource::collection($range->admins()->get()),
            ]);
        }

        throw ValidationException::withMessages([
            'admin_id' => ['Provide either admin_id or range_id to filter results.'],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'admin_id' => ['required', 'string', 'uuid', 'exists:admins,a_id'],
            'range_id' => ['required', 'string', 'uuid', 'exists:ranges,rn_id'],
        ]);

        $admin = Admin::findOrFail($validated['admin_id']);
        $range = Ranges::findOrFail($validated['range_id']);

        if ($admin->ranges()->where('rn_id', $range->rn_id)->exists()) {
            throw ValidationException::withMessages([
                'range_id' => ['This admin already has access to this range.'],
            ]);
        }

        $admin->ranges()->attach($range->rn_id);

        return response()->json([
            'success' => true,
            'message' => 'Range access granted successfully.',
            'data' => [
                'admin' => new AdminResource($admin),
                'range' => new RangeResource($range),
            ],
        ], 201);
    }

    public function destroy(string $adminId, string $rangeId)
    {
        $admin = Admin::findOrFail($adminId);
        $range = Ranges::findOrFail($rangeId);

        $admin->ranges()->detach($range->rn_id);

        return response()->json([
            'success' => true,
            'message' => 'Range access revoked successfully.',
            'data' => null,
        ]);
    }
}
