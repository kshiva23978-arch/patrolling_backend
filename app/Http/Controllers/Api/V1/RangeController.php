<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RangeResource;
use App\Models\Ranges;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RangeController extends Controller
{
    public function index()
    {
        $ranges = Ranges::query()
            ->with('patrollingModes')
            ->latest('rn_created_at')
            ->paginate(15);

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

    public function show(Ranges $range)
    {
        $range->load('patrollingModes');

        return response()->json([
            'success' => true,
            'message' => 'Range retrieved successfully.',
            'data' => new RangeResource($range),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rn_range_id' => ['required', 'string', 'max:100', Rule::unique('ranges', 'rn_range_id')],
            'rn_range_name' => ['required', 'string', 'max:100', Rule::unique('ranges', 'rn_range_name')],
            'rn_category' => ['nullable', Rule::in(Ranges::CATEGORIES)],
            'rn_range_headquarter' => ['required', 'string', 'max:100'],
            'rn_key_activities' => ['nullable', 'string'],
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
        $validated = $request->validate([
            'rn_range_id' => ['sometimes', 'string', 'max:100', Rule::unique('ranges', 'rn_range_id')->ignore($range->rn_id, 'rn_id')],
            'rn_range_name' => ['sometimes', 'string', 'max:100', Rule::unique('ranges', 'rn_range_name')->ignore($range->rn_id, 'rn_id')],
            'rn_category' => ['sometimes', 'nullable', Rule::in(Ranges::CATEGORIES)],
            'rn_range_headquarter' => ['sometimes', 'string', 'max:100'],
            'rn_key_activities' => ['sometimes', 'nullable', 'string'],
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

        if (isset($validated['patrolling_mode_ids'])) {
            $range->patrollingModes()->sync($validated['patrolling_mode_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Range updated successfully.',
            'data' => new RangeResource($range->fresh()->load('patrollingModes')),
        ]);
    }

    public function destroy(Ranges $range)
    {
        $range->delete();

        return response()->json([
            'success' => true,
            'message' => 'Range deleted successfully.',
            'data' => null,
        ]);
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
