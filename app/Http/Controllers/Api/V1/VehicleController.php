<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ScopesToRanges;
use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicles;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
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

        $query = Vehicles::query()
            ->when(isset($validated['range_id']), fn ($q) => $q->where('vh_range_id', $validated['range_id']));
        $query = $this->scopeToAccessibleRanges($query, $request, 'vh_range_id');

        $vehicles = $query->latest('vh_created_at')->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Vehicles retrieved successfully.',
            'data' => VehicleResource::collection($vehicles),
            'meta' => [
                'current_page' => $vehicles->currentPage(),
                'per_page' => $vehicles->perPage(),
                'total' => $vehicles->total(),
                'last_page' => $vehicles->lastPage(),
            ],
        ]);
    }

    /**
     * Active vehicles already on record for a range (Flutter field app) —
     * powers the "Vehicle Number" suggestions in the "Edit Patrol Modes &
     * Vehicles" sheet, so a returning vehicle doesn't need retyping.
     */
    public function forApp(Request $request)
    {
        $validated = $request->validate([
            'range_id' => ['required', 'uuid', 'exists:ranges,rn_id'],
        ]);

        $vehicles = Vehicles::where('vh_range_id', $validated['range_id'])
            ->where('vh_status', true)
            ->orderBy('vh_registration_number')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Vehicles retrieved successfully.',
            'data' => VehicleResource::collection($vehicles),
        ]);
    }

    public function show(Request $request, Vehicles $vehicle)
    {
        $this->assertRangeAccessible($request, $vehicle->vh_range_id);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle retrieved successfully.',
            'data' => new VehicleResource($vehicle),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vh_range_id' => ['required', 'uuid', 'exists:ranges,rn_id'],
            'vh_registration_number' => ['required', 'string', 'max:50', Rule::unique('vehicles', 'vh_registration_number')],
            'vh_type' => ['required', Rule::in(['vehicle', 'boat'])],
            'vh_status' => ['sometimes', 'boolean'],
        ]);

        $this->assertRangeAccessible($request, $validated['vh_range_id']);

        $vehicle = Vehicles::create([
            'vh_range_id' => $validated['vh_range_id'],
            'vh_registration_number' => strtoupper(trim($validated['vh_registration_number'])),
            'vh_type' => $validated['vh_type'],
            'vh_status' => $validated['vh_status'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle created successfully.',
            'data' => new VehicleResource($vehicle),
        ], 201);
    }

    public function update(Request $request, Vehicles $vehicle)
    {
        $this->assertRangeAccessible($request, $vehicle->vh_range_id);

        $validated = $request->validate([
            'vh_registration_number' => ['sometimes', 'string', 'max:50', Rule::unique('vehicles', 'vh_registration_number')->ignore($vehicle->vh_id, 'vh_id')],
            'vh_type' => ['sometimes', Rule::in(['vehicle', 'boat'])],
            'vh_status' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['vh_registration_number'])) {
            $vehicle->vh_registration_number = strtoupper(trim($validated['vh_registration_number']));
        }

        if (isset($validated['vh_type'])) {
            $vehicle->vh_type = $validated['vh_type'];
        }

        if (array_key_exists('vh_status', $validated)) {
            $vehicle->vh_status = $validated['vh_status'];
        }

        $vehicle->save();

        return response()->json([
            'success' => true,
            'message' => 'Vehicle updated successfully.',
            'data' => new VehicleResource($vehicle->fresh()),
        ]);
    }

    public function destroy(Request $request, Vehicles $vehicle)
    {
        $this->assertRangeAccessible($request, $vehicle->vh_range_id);

        return $this->deleteOrConflict($vehicle, 'vehicle');
    }
}
