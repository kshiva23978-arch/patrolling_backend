<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicles;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'range_id' => ['sometimes', 'uuid', 'exists:ranges,rn_id'],
        ]);

        $vehicles = Vehicles::query()
            ->when(isset($validated['range_id']), fn ($q) => $q->where('vh_range_id', $validated['range_id']))
            ->latest('vh_created_at')
            ->paginate(15);

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

    public function show(Vehicles $vehicle)
    {
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

    public function destroy(Vehicles $vehicle)
    {
        $vehicle->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vehicle deleted successfully.',
            'data' => null,
        ]);
    }
}
