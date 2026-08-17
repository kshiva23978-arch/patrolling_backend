<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function index()
    {
        $admins = Admin::query()
            ->select([
                'a_id',
                'a_employee_id',
                'a_role_id',
                'a_designation_id',
                'a_status',
                'a_created_at',
                'a_updated_at',
            ])
            ->latest('a_created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Admins retrieved successfully.',
            'data' => AdminResource::collection($admins),
            'meta' => [
                'current_page' => $admins->currentPage(),
                'per_page' => $admins->perPage(),
                'total' => $admins->total(),
                'last_page' => $admins->lastPage(),
            ],
        ]);
    }

    public function show(Admin $admin)
    {
        return response()->json([
            'success' => true,
            'message' => 'Admin retrieved successfully.',
            'data' => new AdminResource($admin),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'a_employee_id' => ['required', 'string', 'max:255', Rule::unique('admins', 'a_employee_id')],
            'a_password_hash' => ['required', 'string', 'min:8'],
            'a_role_id' => ['nullable', 'string'],
            'a_designation_id' => ['nullable', 'string'],
            'a_status' => ['sometimes', 'boolean'],
        ]);

        $admin = Admin::create([
            'a_employee_id' => trim($validated['a_employee_id']),
            'a_password_hash' => Hash::make($validated['a_password_hash']),
            'a_role_id' => $validated['a_role_id'] ?? null,
            'a_designation_id' => $validated['a_designation_id'] ?? null,
            'a_status' => $validated['a_status'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Admin created successfully.',
            'data' => new AdminResource($admin),
        ], 201);
    }

    public function update(Request $request, Admin $admin)
    {
        $validated = $request->validate([
            'a_employee_id' => ['sometimes', 'string', 'max:255', Rule::unique('admins', 'a_employee_id')->ignore($admin->a_id, 'a_id')],
            'a_password_hash' => ['sometimes', 'string', 'min:8'],
            'a_role_id' => ['sometimes', 'string'],
            'a_designation_id' => ['sometimes', 'string'],
            'a_status' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['a_employee_id'])) {
            $admin->a_employee_id = trim($validated['a_employee_id']);
        }

        if (isset($validated['a_password_hash'])) {
            $admin->a_password_hash = Hash::make($validated['a_password_hash']);
        }

        if (array_key_exists('a_role_id', $validated)) {
            $admin->a_role_id = $validated['a_role_id'];
        }

        if (array_key_exists('a_designation_id', $validated)) {
            $admin->a_designation_id = $validated['a_designation_id'];
        }

        if (array_key_exists('a_status', $validated)) {
            $admin->a_status = $validated['a_status'];
        }

        $admin->save();

        return response()->json([
            'success' => true,
            'message' => 'Admin updated successfully.',
            'data' => new AdminResource($admin->fresh()),
        ]);
    }

    public function destroy(Admin $admin)
    {
        $admin->delete();

        return response()->json([
            'success' => true,
            'message' => 'Admin deleted successfully.',
            'data' => null,
        ]);
    }
}
