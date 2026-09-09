<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResource;
use App\Http\Resources\DestinationResource;
use App\Models\Admin;
use App\Models\Destination;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Which destinations a Department Admin/Ranger admin account is scoped to —
 * the admin-table equivalent of {@see UserDestinationAccessController}. Only
 * makes sense for a `department_admin`/`ranger`-level admin; assigning
 * destinations to a `master_admin`-level admin is harmless but a no-op (it's
 * unrestricted regardless — see `Admin::accessibleDestinationIds`).
 */
class AdminDestinationAccessController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'admin_id' => ['sometimes', 'string', 'uuid', 'exists:admins,a_id'],
            'destination_id' => ['sometimes', 'string', 'uuid', 'exists:destinations,ds_id'],
        ]);

        if (isset($validated['admin_id'])) {
            $admin = Admin::findOrFail($validated['admin_id']);

            return response()->json([
                'success' => true,
                'message' => 'Destinations for admin retrieved successfully.',
                'data' => DestinationResource::collection($admin->destinations()->orderBy('ds_name')->get()),
            ]);
        }

        if (isset($validated['destination_id'])) {
            $destination = Destination::findOrFail($validated['destination_id']);

            return response()->json([
                'success' => true,
                'message' => 'Admins for destination retrieved successfully.',
                'data' => AdminResource::collection($destination->admins()->get()),
            ]);
        }

        throw ValidationException::withMessages([
            'admin_id' => ['Provide either admin_id or destination_id to filter results.'],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'admin_id' => ['required', 'string', 'uuid', 'exists:admins,a_id'],
            'destination_id' => ['required', 'string', 'uuid', 'exists:destinations,ds_id'],
        ]);

        $admin = Admin::findOrFail($validated['admin_id']);
        $destination = Destination::findOrFail($validated['destination_id']);

        if ($admin->destinations()->where('ds_id', $destination->ds_id)->exists()) {
            throw ValidationException::withMessages([
                'destination_id' => ['This admin already has access to this destination.'],
            ]);
        }

        $admin->destinations()->attach($destination->ds_id);

        return response()->json([
            'success' => true,
            'message' => 'Destination access granted successfully.',
            'data' => [
                'admin' => new AdminResource($admin),
                'destination' => new DestinationResource($destination),
            ],
        ], 201);
    }

    public function destroy(string $adminId, string $destinationId)
    {
        $admin = Admin::findOrFail($adminId);
        $destination = Destination::findOrFail($destinationId);

        $admin->destinations()->detach($destination->ds_id);

        return response()->json([
            'success' => true,
            'message' => 'Destination access revoked successfully.',
            'data' => null,
        ]);
    }
}
