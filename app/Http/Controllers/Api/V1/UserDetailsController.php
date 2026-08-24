<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserDetailsResource;
use App\Models\User;
use App\Models\UserDetails;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserDetailsController extends Controller
{
    public function index()
    {
       $userDetails = UserDetails::query()->select([
            'ud_id',
            'ud_user_id',
            'ud_fullname',
            'ud_mobile_number',
            'ud_email',
       ])->latest('ud_created_at')->paginate(15);

       return response()->json([
            'success' => true,
            'message' => 'User details retrieved successfully.',
            'data' => UserDetailsResource::collection($userDetails),
            'meta' => [
                'current_page' => $userDetails->currentPage(),
                'per_page' => $userDetails->perPage(),
                'total' => $userDetails->total(),
                'last_page' => $userDetails->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ud_user_id' => ['required', 'string', 'uuid', 'exists:users,u_id'],
            'ud_fullname' => ['required', 'string', 'max:255'],
            'ud_mobile_number' => ['nullable', 'string', 'max:20'],
            'ud_email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        if (UserDetails::where('ud_user_id', $validated['ud_user_id'])->exists()) {
            throw ValidationException::withMessages([
                'ud_user_id' => ['User details already exist for this user.'],
            ]);
        }

        $userDetails = UserDetails::create([
            'ud_user_id' => trim($validated['ud_user_id']),
            'ud_fullname' => trim($validated['ud_fullname']),
            'ud_mobile_number' => $validated['ud_mobile_number'] ?? null,
            'ud_email' => $validated['ud_email'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User details created successfully.',
            'data' => new UserDetailsResource($userDetails),
        ], 201);
    }

    public function show(UserDetails $userDetails)
    {
        return response()->json([
            'success' => true,
            'message' => 'User details retrieved successfully.',
            'data' => new UserDetailsResource($userDetails),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'ud_user_id' => ['required', 'string', 'uuid', 'exists:users,u_id'],
            'ud_fullname' => ['sometimes', 'string', 'max:255'],
            'ud_mobile_number' => ['sometimes', 'string', 'max:20'],
            'ud_email' => ['sometimes', 'string', 'email', 'max:255'],
        ]);

        $userDetails = UserDetails::where('ud_user_id', $validated['ud_user_id'])->firstOrFail();

        $userDetails->fill([
            'ud_fullname' => $validated['ud_fullname'] ?? $userDetails->ud_fullname,
            'ud_mobile_number' => $validated['ud_mobile_number'] ?? $userDetails->ud_mobile_number,
            'ud_email' => $validated['ud_email'] ?? $userDetails->ud_email,
        ]);

        $userDetails->save();

        return response()->json([
            'success' => true,
            'message' => 'User details updated successfully.',
            'data' => new UserDetailsResource($userDetails->fresh()),
        ]);
    }

    public function destroy(UserDetails $userDetails)
    {
        return $this->deleteOrConflict($userDetails, 'user details');
    }
}

