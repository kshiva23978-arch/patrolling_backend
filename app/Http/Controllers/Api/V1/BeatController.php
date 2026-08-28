<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ScopesToRanges;
use App\Http\Controllers\Controller;
use App\Http\Resources\BeatResource;
use App\Models\Beats;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BeatController extends Controller
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

        $query = Beats::query()
            ->when(isset($validated['range_id']), fn ($q) => $q->where('bt_range_id', $validated['range_id']));
        $query = $this->scopeToAccessibleRanges($query, $request, 'bt_range_id');

        $beats = $query->latest('bt_created_at')->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Beats retrieved successfully.',
            'data' => BeatResource::collection($beats),
            'meta' => [
                'current_page' => $beats->currentPage(),
                'per_page' => $beats->perPage(),
                'total' => $beats->total(),
                'last_page' => $beats->lastPage(),
            ],
        ]);
    }

    /**
     * Active beats for the given range (Flutter field app dropdown).
     */
    public function forApp(Request $request)
    {
        $validated = $request->validate([
            'range_id' => ['required', 'uuid', 'exists:ranges,rn_id'],
        ]);

        $beats = Beats::where('bt_range_id', $validated['range_id'])
            ->where('bt_status', true)
            ->orderBy('bt_name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Beats retrieved successfully.',
            'data' => BeatResource::collection($beats),
        ]);
    }

    public function show(Request $request, Beats $beat)
    {
        $this->assertRangeAccessible($request, $beat->bt_range_id);

        return response()->json([
            'success' => true,
            'message' => 'Beat retrieved successfully.',
            'data' => new BeatResource($beat),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bt_range_id' => ['required', 'uuid', 'exists:ranges,rn_id'],
            'bt_name' => ['required', 'string', 'max:255'],
            'bt_status' => ['sometimes', 'boolean'],
            'bt_boundary' => ['sometimes', 'nullable', 'array'],
            'bt_boundary.type' => ['required_with:bt_boundary', 'in:Polygon'],
            'bt_boundary.coordinates' => ['required_with:bt_boundary', 'array', 'min:1'],
        ]);

        $this->assertRangeAccessible($request, $validated['bt_range_id']);

        $exists = Beats::where('bt_range_id', $validated['bt_range_id'])
            ->where('bt_name', trim($validated['bt_name']))
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A beat with this name already exists for the selected range.',
                'data' => null,
            ], 422);
        }

        $beat = Beats::create([
            'bt_range_id' => $validated['bt_range_id'],
            'bt_name' => trim($validated['bt_name']),
            'bt_status' => $validated['bt_status'] ?? true,
        ]);

        if (array_key_exists('bt_boundary', $validated)) {
            $this->setBoundary($beat, $validated['bt_boundary']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Beat created successfully.',
            'data' => new BeatResource($beat),
        ], 201);
    }

    public function update(Request $request, Beats $beat)
    {
        $this->assertRangeAccessible($request, $beat->bt_range_id);

        $validated = $request->validate([
            'bt_name' => ['sometimes', 'string', 'max:255'],
            'bt_status' => ['sometimes', 'boolean'],
            'bt_boundary' => ['sometimes', 'nullable', 'array'],
            'bt_boundary.type' => ['required_with:bt_boundary', 'in:Polygon'],
            'bt_boundary.coordinates' => ['required_with:bt_boundary', 'array', 'min:1'],
        ]);

        if (isset($validated['bt_name'])) {
            $beat->bt_name = trim($validated['bt_name']);
        }

        if (array_key_exists('bt_status', $validated)) {
            $beat->bt_status = $validated['bt_status'];
        }

        $beat->save();

        if (array_key_exists('bt_boundary', $validated)) {
            $this->setBoundary($beat, $validated['bt_boundary']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Beat updated successfully.',
            'data' => new BeatResource($beat->fresh()),
        ]);
    }

    public function destroy(Request $request, Beats $beat)
    {
        $this->assertRangeAccessible($request, $beat->bt_range_id);

        return $this->deleteOrConflict($beat, 'beat');
    }

    /**
     * [$geojson] is `null` to clear the boundary, or a validated GeoJSON
     * `{type: "Polygon", coordinates: [...]}` — the coordinates themselves
     * (ring closure, winding order, in-range lat/lng) are only checked by
     * `ST_GeomFromGeoJSON` itself, so a malformed shape surfaces as a
     * validation error here rather than a raw 500.
     */
    private function setBoundary(Beats $beat, ?array $geojson): void
    {
        try {
            $beat->setBoundary($geojson);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'bt_boundary' => 'That boundary shape could not be saved. Check that it is a valid polygon.',
            ]);
        }
    }
}
