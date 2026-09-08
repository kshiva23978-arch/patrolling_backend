<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DestinationResource;
use App\Models\Destination;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserDestinationAccessController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['sometimes', 'string', 'uuid', 'exists:users,u_id'],
        ]);

        if (! isset($validated['user_id'])) {
            throw ValidationException::withMessages([
                'user_id' => ['Provide user_id to filter results.'],
            ]);
        }

        $user = User::findOrFail($validated['user_id']);

        return response()->json([
            'success' => true,
            'message' => 'Destinations for user retrieved successfully.',
            'data' => DestinationResource::collection($user->destinations()->orderBy('ds_name')->get()),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'uuid', 'exists:users,u_id'],
            'destination_id' => ['required', 'string', 'uuid', 'exists:destinations,ds_id'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $destination = Destination::findOrFail($validated['destination_id']);

        if ($user->destinations()->where('ds_id', $destination->ds_id)->exists()) {
            throw ValidationException::withMessages([
                'destination_id' => ['This user already has access to this destination.'],
            ]);
        }

        $user->destinations()->attach($destination->ds_id);

        return response()->json([
            'success' => true,
            'message' => 'Destination access granted successfully.',
            'data' => ['destination' => new DestinationResource($destination)],
        ], 201);
    }

    public function destroy(string $userId, string $destinationId)
    {
        $user = User::findOrFail($userId);
        $destination = Destination::findOrFail($destinationId);
        $user->destinations()->detach($destination->ds_id);

        return response()->json([
            'success' => true,
            'message' => 'Destination access revoked successfully.',
            'data' => null,
        ]);
    }
}
