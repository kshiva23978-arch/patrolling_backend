<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->select([
                'u_id',
                'u_employee_id',
                'u_role_id',
                'u_designation_id',
                'u_status',
                'u_created_at',
                'u_updated_at',
            ])
            ->latest('u_created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully.',
            'data' => UserResource::collection($users),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function show(User $user)
    {
        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully.',
            'data' => new UserResource($user),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'u_employee_id' => ['required', 'string', 'max:255', Rule::unique('users', 'u_employee_id')],
            'u_password_hash' => ['required', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            'u_role_id' => ['nullable', 'string'],
            'u_designation_id' => ['nullable', 'string'],
            'u_status' => ['sometimes', 'boolean'],
        ]);

        $user = User::create([
            'u_employee_id' => trim($validated['u_employee_id']),
            'u_password_hash' => Hash::make($validated['u_password_hash']),
            'u_role_id' => $validated['u_role_id'] ?? null,
            'u_designation_id' => $validated['u_designation_id'] ?? null,
            'u_status' => $validated['u_status'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => new UserResource($user),
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'u_employee_id' => ['sometimes', 'string', 'max:255', Rule::unique('users', 'u_employee_id')->ignore($user->u_id, 'u_id')],
            'u_password_hash' => ['sometimes', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            'u_role_id' => ['sometimes', 'string'],
            'u_designation_id' => ['sometimes', 'string'],
            'u_status' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['u_employee_id'])) {
            $user->u_employee_id = trim($validated['u_employee_id']);
        }

        if (isset($validated['u_password_hash'])) {
            $user->u_password_hash = Hash::make($validated['u_password_hash']);
        }

        if (array_key_exists('u_role_id', $validated)) {
            $user->u_role_id = $validated['u_role_id'];
        }

        if (array_key_exists('u_designation_id', $validated)) {
            $user->u_designation_id = $validated['u_designation_id'];
        }

        if (array_key_exists('u_status', $validated)) {
            $user->u_status = $validated['u_status'];
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => new UserResource($user->fresh()),
        ]);
    }

    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
            'data' => null,
        ]);
    }
}
