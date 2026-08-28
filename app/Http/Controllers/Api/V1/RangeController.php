<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ScopesToRanges;
use App\Http\Controllers\Controller;
use App\Http\Resources\RangeResource;
use App\Models\Ranges;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RangeController extends Controller
{
    use ScopesToRanges;

    public function index(Request $request)
    {
        $query = Ranges::query()->with('patrollingModes');
        $query = $this->scopeToAccessibleRanges($query, $request, 'rn_id');

        $ranges = $query->latest('rn_created_at')->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Ranges retrieved successfully.',
            'data' => RangeResource::collection($ranges),
            'meta' => [
                'current_page' => $ranges->currentPage(),
                'per_page' => $ranges->perPage(),
                'total' => $ranges->total(),
                'last_page' => $ranges->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Ranges $range)
    {
        $this->assertRangeAccessible($request, $range->rn_id);

        $range->load('patrollingModes');

        return response()->json([
            'success' => true,
            'message' => 'Range retrieved successfully.',
            'data' => new RangeResource($range),
        ]);
    }

    public function store(Request $request)
    {
        // Creating a new range means creating a new department — reserved
        // for an unrestricted (master) admin, never a department_admin/
        // ranger scoped to their existing range(s).
        if (! $this->isUnrestrictedAdmin($request)) {
            abort(403, 'Only a Master Admin can create ranges.');
        }

        $validated = $request->validate([
            'rn_range_id' => ['required', 'string', 'max:100', Rule::unique('ranges', 'rn_range_id')],
            'rn_range_name' => ['required', 'string', 'max:100', Rule::unique('ranges', 'rn_range_name')],
            'rn_category' => ['nullable', Rule::in(Ranges::CATEGORIES)],
            'rn_range_headquarter' => ['required', 'string', 'max:100'],
            'rn_key_activities' => ['nullable', 'string'],
            'rn_boundary' => ['sometimes', 'nullable', 'array'],
            'rn_boundary.type' => ['required_with:rn_boundary', 'in:Polygon'],
            'rn_boundary.coordinates' => ['required_with:rn_boundary', 'array', 'min:1'],
            'patrolling_mode_ids' => ['sometimes', 'array'],
            'patrolling_mode_ids.*' => ['string', 'uuid', 'exists:patrolling_modes,pm_id'],
        ]);

        $range = Ranges::create([
            'rn_range_id' => trim($validated['rn_range_id']),
            'rn_range_name' => trim($validated['rn_range_name']),
            'rn_category' => $validated['rn_category'] ?? null,
            'rn_range_headquarter' => trim($validated['rn_range_headquarter']),
            'rn_key_activities' => $validated['rn_key_activities'] ?? null,
        ]);

        if (array_key_exists('rn_boundary', $validated)) {
            $this->setBoundary($range, $validated['rn_boundary']);
        }

        if (isset($validated['patrolling_mode_ids'])) {
            $range->patrollingModes()->sync($validated['patrolling_mode_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Range created successfully.',
            'data' => new RangeResource($range->load('patrollingModes')),
        ], 201);
    }

    public function update(Request $request, Ranges $range)
    {
        $this->assertRangeAccessible($request, $range->rn_id);

        $validated = $request->validate([
            'rn_range_id' => ['sometimes', 'string', 'max:100', Rule::unique('ranges', 'rn_range_id')->ignore($range->rn_id, 'rn_id')],
            'rn_range_name' => ['sometimes', 'string', 'max:100', Rule::unique('ranges', 'rn_range_name')->ignore($range->rn_id, 'rn_id')],
            'rn_category' => ['sometimes', 'nullable', Rule::in(Ranges::CATEGORIES)],
            'rn_range_headquarter' => ['sometimes', 'string', 'max:100'],
            'rn_key_activities' => ['sometimes', 'nullable', 'string'],
            'rn_boundary' => ['sometimes', 'nullable', 'array'],
            'rn_boundary.type' => ['required_with:rn_boundary', 'in:Polygon'],
            'rn_boundary.coordinates' => ['required_with:rn_boundary', 'array', 'min:1'],
            'patrolling_mode_ids' => ['sometimes', 'array'],
            'patrolling_mode_ids.*' => ['string', 'uuid', 'exists:patrolling_modes,pm_id'],
        ]);

        if (isset($validated['rn_range_id'])) {
            $range->rn_range_id = trim($validated['rn_range_id']);
        }

        if (isset($validated['rn_range_name'])) {
            $range->rn_range_name = trim($validated['rn_range_name']);
        }

        if (array_key_exists('rn_category', $validated)) {
            $range->rn_category = $validated['rn_category'];
        }

        if (isset($validated['rn_range_headquarter'])) {
            $range->rn_range_headquarter = trim($validated['rn_range_headquarter']);
        }

        if (array_key_exists('rn_key_activities', $validated)) {
            $range->rn_key_activities = $validated['rn_key_activities'];
        }

        $range->save();

        if (array_key_exists('rn_boundary', $validated)) {
            $this->setBoundary($range, $validated['rn_boundary']);
        }

        if (isset($validated['patrolling_mode_ids'])) {
            $range->patrollingModes()->sync($validated['patrolling_mode_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Range updated successfully.',
            'data' => new RangeResource($range->fresh()->load('patrollingModes')),
        ]);
    }

    public function destroy(Request $request, Ranges $range)
    {
        if (! $this->isUnrestrictedAdmin($request)) {
            abort(403, 'Only a Master Admin can delete ranges.');
        }

        return $this->deleteOrConflict($range, 'range');
    }

    /** See {@see \App\Http\Controllers\Api\V1\BeatController::setBoundary()}. */
    private function setBoundary(Ranges $range, ?array $geojson): void
    {
        try {
            $range->setBoundary($geojson);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'rn_boundary' => 'That boundary shape could not be saved. Check that it is a valid polygon.',
            ]);
        }
    }

    /**
     * Ranges assigned to the currently authenticated user (Flutter guard app).
     */
    public function myRanges(Request $request)
    {
        $ranges = $request->user()->ranges()->with('patrollingModes')->get();

        return response()->json([
            'success' => true,
            'message' => 'Assigned ranges retrieved successfully.',
            'data' => RangeResource::collection($ranges),
        ]);
    }
}
