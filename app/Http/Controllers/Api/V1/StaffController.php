<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ScopesToRanges;
use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use App\Models\Staff;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    use ScopesToRanges;

    public function index(Request $request)
    {
        $validated = $request->validate([
            'range_id' => ['sometimes', 'uuid', 'exists:ranges,rn_id'],
        ]);

        if (isset($validated['range_id'])) {
            $this->assertRangeAccessible($request, $validated['range_id']);
        }

        $query = Staff::query()
            ->when(isset($validated['range_id']), fn ($q) => $q->where('st_range_id', $validated['range_id']));
        $query = $this->scopeToAccessibleRanges($query, $request, 'st_range_id');

        $staff = $query->orderBy('st_name')->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Staff retrieved successfully.',
            'data' => StaffResource::collection($staff),
            'meta' => [
                'current_page' => $staff->currentPage(),
                'per_page' => $staff->perPage(),
                'total' => $staff->total(),
                'last_page' => $staff->lastPage(),
            ],
        ]);
    }

    /**
     * Active staff for the given range (Flutter field app dropdown) — same
     * pattern as {@see BeatController::forApp}/{@see VehicleController::forApp}
     * for populating a "staff deployed" picker instead of free text.
     */
    public function forApp(Request $request)
    {
        $validated = $request->validate([
            'range_id' => ['required', 'uuid', 'exists:ranges,rn_id'],
        ]);

        $staff = Staff::where('st_range_id', $validated['range_id'])
            ->where('st_status', true)
            ->orderBy('st_name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Staff retrieved successfully.',
            'data' => StaffResource::collection($staff),
        ]);
    }

    public function show(Request $request, Staff $staff)
    {
        $this->assertRangeAccessible($request, $staff->st_range_id);

        return response()->json([
            'success' => true,
            'message' => 'Staff member retrieved successfully.',
            'data' => new StaffResource($staff),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'st_name' => ['required', 'string', 'max:150'],
            'st_designation_id' => ['nullable', 'uuid', 'exists:designations,d_id'],
            'st_range_id' => ['required', 'uuid', 'exists:ranges,rn_id'],
            'st_status' => ['sometimes', 'boolean'],
        ]);

        $this->assertRangeAccessible($request, $validated['st_range_id']);

        $staff = Staff::create([
            'st_name' => trim($validated['st_name']),
            'st_designation_id' => $validated['st_designation_id'] ?? null,
            'st_range_id' => $validated['st_range_id'],
            'st_status' => $validated['st_status'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Staff member created successfully.',
            'data' => new StaffResource($staff),
        ], 201);
    }

    public function update(Request $request, Staff $staff)
    {
        $this->assertRangeAccessible($request, $staff->st_range_id);

        $validated = $request->validate([
            'st_name' => ['sometimes', 'string', 'max:150'],
            'st_designation_id' => ['sometimes', 'nullable', 'uuid', 'exists:designations,d_id'],
            'st_range_id' => ['sometimes', 'uuid', 'exists:ranges,rn_id'],
            'st_status' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['st_range_id'])) {
            $this->assertRangeAccessible($request, $validated['st_range_id']);
        }

        if (isset($validated['st_name'])) {
            $staff->st_name = trim($validated['st_name']);
        }

        if (array_key_exists('st_designation_id', $validated)) {
            $staff->st_designation_id = $validated['st_designation_id'];
        }

        if (isset($validated['st_range_id'])) {
            $staff->st_range_id = $validated['st_range_id'];
        }

        if (array_key_exists('st_status', $validated)) {
            $staff->st_status = $validated['st_status'];
        }

        $staff->save();

        return response()->json([
            'success' => true,
            'message' => 'Staff member updated successfully.',
            'data' => new StaffResource($staff->fresh()),
        ]);
    }

    public function destroy(Request $request, Staff $staff)
    {
        $this->assertRangeAccessible($request, $staff->st_range_id);

        return $this->deleteOrConflict($staff, 'staff member');
    }
}
