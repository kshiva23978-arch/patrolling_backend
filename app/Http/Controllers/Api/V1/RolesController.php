<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Roles;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RolesController extends Controller
{
    public function index()
    {
        $roles = Roles::query()
            ->select([
                'ro_id',
                'ro_name',
                'ro_description',
                'ro_status',
                'ro_level',
                'ro_created_at',
                'ro_updated_at',
            ])
            ->latest('ro_created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Roles retrieved successfully.',
            'data' => RoleResource::collection($roles),
            'meta' => [
                'current_page' => $roles->currentPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
                'last_page' => $roles->lastPage(),
            ],
        ]);
    }

    public function show(Roles $role)
    {
        return response()->json([
            'success' => true,
            'message' => 'Role retrieved successfully.',
            'data' => new RoleResource($role),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ro_name' => ['required', 'string', 'max:255', Rule::unique('roles', 'ro_name')],
            'ro_description' => ['nullable', 'string'],
            'ro_status' => ['sometimes', 'boolean'],
            'ro_level' => ['nullable', Rule::in(Roles::ADMIN_LEVELS)],
            ...$this->permissionsRules(),
        ]);

        $role = Roles::create([
            'ro_name' => trim($validated['ro_name']),
            'ro_description' => $validated['ro_description'] ?? null,
            'ro_status' => $validated['ro_status'] ?? true,
            'ro_permissions' => $validated['ro_permissions'] ?? null,
            'ro_level' => $validated['ro_level'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully.',
            'data' => new RoleResource($role),
        ], 201);
    }

    public function update(Request $request, Roles $role)
    {
        $validated = $request->validate([
            'ro_name' => ['sometimes', 'string', 'max:255', Rule::unique('roles', 'ro_name')->ignore($role->ro_id, 'ro_id')],
            'ro_description' => ['sometimes', 'string'],
            'ro_status' => ['sometimes', 'boolean'],
            'ro_level' => ['sometimes', 'nullable', Rule::in(Roles::ADMIN_LEVELS)],
            ...$this->permissionsRules(),
        ]);

        if (isset($validated['ro_name'])) {
            $role->ro_name = trim($validated['ro_name']);
        }

        if (array_key_exists('ro_description', $validated)) {
            $role->ro_description = $validated['ro_description'];
        }

        if (array_key_exists('ro_status', $validated)) {
            $role->ro_status = $validated['ro_status'];
        }

        if (array_key_exists('ro_permissions', $validated)) {
            $role->ro_permissions = $validated['ro_permissions'];
        }

        if (array_key_exists('ro_level', $validated)) {
            $role->ro_level = $validated['ro_level'];
        }

        $role->save();

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully.',
            'data' => new RoleResource($role->fresh()),
        ]);
    }

    public function destroy(Roles $role)
    {
        return $this->deleteOrConflict($role, 'role');
    }

    /**
     * Validates the shape of `ro_permissions` (see `Roles::hasAdminPermission`/
     * `hasAppFeature` for how it's read): `null` means unrestricted, so it's
     * only shape-checked when actually present — an admin explicitly
     * clearing a role's restrictions by sending `null` is valid too.
     */
    private function permissionsRules(): array
    {
        return [
            'ro_permissions' => ['nullable', 'array'],
            'ro_permissions.admin' => ['nullable', 'array'],
            'ro_permissions.admin.*' => ['array'],
            'ro_permissions.admin.*.view' => ['sometimes', 'boolean'],
            'ro_permissions.admin.*.manage' => ['sometimes', 'boolean'],
            'ro_permissions.app' => ['nullable', 'array'],
            'ro_permissions.app.*' => ['boolean'],
        ];
    }
}
