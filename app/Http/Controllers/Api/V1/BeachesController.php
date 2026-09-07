<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BeachResource;
use App\Models\Beach;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BeachesController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'destination_id' => ['sometimes', 'uuid', 'exists:destinations,ds_id'],
        ]);

        $beaches = Beach::query()
            ->when(isset($validated['destination_id']), fn ($q) => $q->where('bc_destination_id', $validated['destination_id']))
            ->latest('bc_created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Beaches retrieved successfully.',
            'data' => BeachResource::collection($beaches),
            'meta' => [
                'current_page' => $beaches->currentPage(),
                'per_page' => $beaches->perPage(),
                'total' => $beaches->total(),
                'last_page' => $beaches->lastPage(),
            ],
        ]);
    }

    /** Active beaches for the given destination (Flutter field app dropdown). */
    public function forApp(Request $request)
    {
        $validated = $request->validate([
            'destination_id' => ['required', 'uuid', 'exists:destinations,ds_id'],
        ]);

        $beaches = Beach::where('bc_destination_id', $validated['destination_id'])
            ->where('bc_status', true)
            ->orderBy('bc_name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Beaches retrieved successfully.',
            'data' => BeachResource::collection($beaches),
        ]);
    }

    public function show(Beach $beach)
    {
        return response()->json([
            'success' => true,
            'message' => 'Beach retrieved successfully.',
            'data' => new BeachResource($beach),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'destination_id' => ['required', 'uuid', 'exists:destinations,ds_id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'boolean'],
        ]);

        $exists = Beach::where('bc_destination_id', $validated['destination_id'])
            ->where('bc_name', trim($validated['name']))
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A beach with this name already exists for the selected destination.',
                'data' => null,
            ], 422);
        }

        $beach = Beach::create([
            'bc_destination_id' => $validated['destination_id'],
            'bc_name' => trim($validated['name']),
            'bc_status' => $validated['status'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Beach created successfully.',
            'data' => new BeachResource($beach),
        ], 201);
    }

    public function update(Request $request, Beach $beach)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['name'])) {
            $beach->bc_name = trim($validated['name']);
        }
        if (array_key_exists('status', $validated)) {
            $beach->bc_status = $validated['status'];
        }
        $beach->save();

        return response()->json([
            'success' => true,
            'message' => 'Beach updated successfully.',
            'data' => new BeachResource($beach->fresh()),
        ]);
    }

    public function destroy(Beach $beach)
    {
        return $this->deleteOrConflict($beach, 'beach');
    }
}
