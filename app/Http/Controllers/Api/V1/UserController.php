<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ScopesToRanges;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    use ScopesToRanges;

    /**
     * Staff visible to [$request]'s admin: unrestricted for a Master
     * Admin, otherwise only staff with at least one range in common with
     * the admin's own assigned ranges — plus staff with *no* range
     * assigned yet, so a Department Admin/Ranger doesn't immediately lose
     * sight of a staff record they just created, before it's been
     * assigned to a range via `UserRangeAccessController`.
     */
    public function index(Request $request)
    {
        $query = User::query()
            ->select([
                'u_id',
                'u_employee_id',
                'u_role_id',
                'u_designation_id',
                'u_has_login',
                'u_status',
                'u_created_at',
                'u_updated_at',
            ]);

        $rangeIds = $this->accessibleRangeIds($request);
        if ($rangeIds !== null) {
            $query->where(function ($q) use ($rangeIds) {
                $q->whereDoesntHave('ranges')
                    ->orWhereHas('ranges', fn ($q2) => $q2->whereIn('rn_id', $rangeIds));
            });
        }

        $users = $query->latest('u_created_at')->paginate(15);

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

    public function show(Request $request, User $user)
    {
        $this->assertStaffAccessible($request, $user);

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully.',
            'data' => new UserResource($user),
        ]);
    }

    public function store(Request $request)
    {
        // `u_password_hash` arrives as the SHA-256 hex digest of the
        // ranger's chosen password (see the app's `hashPassword` helper) —
        // never the plaintext, so there's nothing here to check complexity
        // (mixed case/symbols/etc.) against server-side; that has to be
        // enforced client-side, before hashing. `min:8` is just a sanity
        // floor against an empty/garbage value.
        //
        // `u_has_login` lets a Ranger add a staff record purely for
        // record-keeping (named staff with no login of their own) — when
        // `false`, employee id/password are ignored even if sent.
        // Defaulted (rather than left absent) before validation so
        // `required_if:u_has_login,true` below sees it either way — Laravel's
        // `required_if` only matches a field actually present with that
        // value, not an implied default.
        $request->merge(['u_has_login' => $request->boolean('u_has_login', true)]);

        $validated = $request->validate([
            'u_has_login' => ['sometimes', 'boolean'],
            'u_employee_id' => ['required_if:u_has_login,true', 'nullable', 'string', 'max:255', Rule::unique('users', 'u_employee_id')],
            'u_password_hash' => ['required_if:u_has_login,true', 'nullable', 'string', 'min:8'],
            'u_role_id' => ['nullable', 'string'],
            'u_designation_id' => ['nullable', 'string'],
            'u_status' => ['sometimes', 'boolean'],
            'range_id' => ['sometimes', 'uuid', 'exists:ranges,rn_id'],
        ]);

        $hasLogin = $validated['u_has_login'] ?? true;

        if (isset($validated['range_id'])) {
            $this->assertRangeAccessible($request, $validated['range_id']);
        }

        $user = User::create([
            'u_employee_id' => $hasLogin ? trim($validated['u_employee_id']) : null,
            'u_password_hash' => $hasLogin ? Hash::make($validated['u_password_hash']) : null,
            'u_has_login' => $hasLogin,
            'u_role_id' => $validated['u_role_id'] ?? null,
            'u_designation_id' => $validated['u_designation_id'] ?? null,
            'u_status' => $validated['u_status'] ?? true,
        ]);

        if (isset($validated['range_id'])) {
            $user->ranges()->attach($validated['range_id']);
        }

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => new UserResource($user),
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $this->assertStaffAccessible($request, $user);

        $validated = $request->validate([
            'u_has_login' => ['sometimes', 'boolean'],
            'u_employee_id' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('users', 'u_employee_id')->ignore($user->u_id, 'u_id')],
            'u_password_hash' => ['sometimes', 'nullable', 'string', 'min:8'],
            'u_role_id' => ['sometimes', 'string'],
            'u_designation_id' => ['sometimes', 'string'],
            'u_status' => ['sometimes', 'boolean'],
        ]);

        $hasLogin = $validated['u_has_login'] ?? $user->u_has_login;

        if ($hasLogin && ! ($validated['u_employee_id'] ?? $user->u_employee_id)) {
            throw ValidationException::withMessages([
                'u_employee_id' => 'An employee ID is required when this staff member has app login.',
            ]);
        }

        if (array_key_exists('u_has_login', $validated)) {
            $user->u_has_login = $hasLogin;
            if (! $hasLogin) {
                $user->u_employee_id = null;
                $user->u_password_hash = null;
            }
        }

        if ($hasLogin && isset($validated['u_employee_id'])) {
            $user->u_employee_id = trim($validated['u_employee_id']);
        }

        if ($hasLogin && isset($validated['u_password_hash'])) {
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

    public function destroy(Request $request, User $user)
    {
        $this->assertStaffAccessible($request, $user);

        return $this->deleteOrConflict($user, 'user');
    }

    /**
     * Aborts with a 403 unless [$request]'s admin can see/manage [$user] —
     * same "any shared range, or no range yet" rule as `index`'s filter.
     */
    private function assertStaffAccessible(Request $request, User $user): void
    {
        $rangeIds = $this->accessibleRangeIds($request);
        if ($rangeIds === null) {
            return;
        }

        $userRangeIds = $user->ranges()->pluck('rn_id')->all();
        if ($userRangeIds === [] || array_intersect($userRangeIds, $rangeIds) !== []) {
            return;
        }

        abort(403, 'You do not have access to this staff member.');
    }
}
