<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DestinationResource;
use App\Models\Destination;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DestinationsController extends Controller
{
    public function index()
    {
        $destinations = Destination::query()->latest('ds_created_at')->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Destinations retrieved successfully.',
            'data' => DestinationResource::collection($destinations),
            'meta' => [
                'current_page' => $destinations->currentPage(),
                'per_page' => $destinations->perPage(),
                'total' => $destinations->total(),
                'last_page' => $destinations->lastPage(),
            ],
        ]);
    }

    /** Active destinations (Flutter field app dropdown when creating an activity). */
    public function forApp()
    {
        $destinations = Destination::where('ds_status', true)->orderBy('ds_name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Destinations retrieved successfully.',
            'data' => DestinationResource::collection($destinations),
        ]);
    }

    /**
     * Active destinations this ranger is assigned to — scoped via
     * `user_beach_access`/`User::destinations`, same as
     * `RangeController::myRanges` scopes ranges. Only for the beach cleaning
     * module's create-drive picker; unlike {@see forApp} (the standalone
     * Activities module's own destination picker, deliberately left
     * unscoped), a ranger has no "unrestricted" default here — no
     * assignment means no destinations show up at all.
     */
    public function forBeachCleaning(Request $request)
    {
        $destinations = $request->user()->destinations()->where('ds_status', true)->orderBy('ds_name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Destinations retrieved successfully.',
            'data' => DestinationResource::collection($destinations),
        ]);
    }

    public function show(Destination $destination)
    {
        return response()->json([
            'success' => true,
            'message' => 'Destination retrieved successfully.',
            'data' => new DestinationResource($destination),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('destinations', 'ds_name')],
            'status' => ['sometimes', 'boolean'],
        ]);

        $destination = Destination::create([
            'ds_name' => trim($validated['name']),
            'ds_status' => $validated['status'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Destination created successfully.',
            'data' => new DestinationResource($destination),
        ], 201);
    }

    public function update(Request $request, Destination $destination)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('destinations', 'ds_name')->ignore($destination->ds_id, 'ds_id')],
            'status' => ['sometimes', 'boolean'],
        ]);

        $destination->ds_name = trim($validated['name']);
        if (array_key_exists('status', $validated)) {
            $destination->ds_status = $validated['status'];
        }
        $destination->save();

        return response()->json([
            'success' => true,
            'message' => 'Destination updated successfully.',
            'data' => new DestinationResource($destination->fresh()),
        ]);
    }

    public function destroy(Destination $destination)
    {
        return $this->deleteOrConflict($destination, 'destination');
    }
}
