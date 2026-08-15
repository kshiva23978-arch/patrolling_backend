<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RangeResource;
use App\Http\Resources\UserResource;
use App\Models\Ranges;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserRangeAccessController extends Controller
{
    /**
     * List range access. Filter by user_id or range_id.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['sometimes', 'string', 'uuid', 'exists:users,u_id'],
            'range_id' => ['sometimes', 'string', 'uuid', 'exists:ranges,rn_id'],
        ]);

        if (isset($validated['user_id'])) {
            $user = User::findOrFail($validated['user_id']);

            return response()->json([
                'success' => true,
                'message' => 'Ranges for user retrieved successfully.',
                'data' => RangeResource::collection($user->ranges()->with('patrollingModes')->get()),
            ]);
        }

        if (isset($validated['range_id'])) {
            $range = Ranges::findOrFail($validated['range_id']);

            return response()->json([
                'success' => true,
                'message' => 'Users for range retrieved successfully.',
                'data' => UserResource::collection($range->users()->get()),
            ]);
        }

        throw ValidationException::withMessages([
            'user_id' => ['Provide either user_id or range_id to filter results.'],
        ]);
    }

    /**
     * Assign a user to a range.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'uuid', 'exists:users,u_id'],
            'range_id' => ['required', 'string', 'uuid', 'exists:ranges,rn_id'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $range = Ranges::findOrFail($validated['range_id']);

        if ($user->ranges()->where('rn_id', $range->rn_id)->exists()) {
            throw ValidationException::withMessages([
                'range_id' => ['This user already has access to this range.'],
            ]);
        }

        $user->ranges()->attach($range->rn_id);

        return response()->json([
            'success' => true,
            'message' => 'Range access granted successfully.',
            'data' => [
                'user' => new UserResource($user),
                'range' => new RangeResource($range),
            ],
        ], 201);
    }

    /**
     * Revoke a user's access to a range.
     */
    public function destroy(string $userId, string $rangeId)
    {
        $user = User::findOrFail($userId);
        $range = Ranges::findOrFail($rangeId);

        $user->ranges()->detach($range->rn_id);

        return response()->json([
            'success' => true,
            'message' => 'Range access revoked successfully.',
            'data' => null,
        ]);
    }
}
