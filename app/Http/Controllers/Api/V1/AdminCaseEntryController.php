<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ScopesToRanges;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminCaseEntryResource;
use App\Models\CaseEntry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Read-only visibility into every ranger's standalone Cases, for the admin
 * panel's "Cases" section — unlike {@see \App\Http\Controllers\Api\V1\CaseEntryController},
 * which is scoped to the field app's own authenticated ranger. Mirrors
 * {@see AdminPatrolEntryController} for the Patrol module.
 */
class AdminCaseEntryController extends Controller
{
    use ScopesToRanges;

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in([CaseEntry::STATUS_PENDING, CaseEntry::STATUS_IN_PROGRESS, CaseEntry::STATUS_COMPLETED])],
            'range_id' => ['sometimes', 'uuid', 'exists:ranges,rn_id'],
        ]);

        if (isset($validated['range_id'])) {
            $this->assertRangeAccessible($request, $validated['range_id']);
        }

        $query = CaseEntry::query()
            ->with(['range', 'beat', 'modes', 'vehicles.vehicle', 'leader.details', 'incidents.media', 'filings.media'])
            ->when(
                $validated['status'] ?? null,
                fn ($q, $status) => $q->where('ce_status', $status)
            )
            ->when(
                $validated['range_id'] ?? null,
                fn ($q, $rangeId) => $q->where('ce_range_id', $rangeId)
            );
        $query = $this->scopeToAccessibleRanges($query, $request, 'ce_range_id');

        $cases = $query
            ->orderByRaw("CASE ce_status WHEN 'in_progress' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->latest('ce_created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Cases retrieved successfully.',
            'data' => AdminCaseEntryResource::collection($cases),
            'meta' => [
                'current_page' => $cases->currentPage(),
                'per_page' => $cases->perPage(),
                'total' => $cases->total(),
                'last_page' => $cases->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, CaseEntry $case)
    {
        $this->assertRangeAccessible($request, $case->ce_range_id);

        $case->load(['range', 'beat', 'modes', 'vehicles.vehicle', 'leader.details', 'incidents.media', 'filings.media', 'notes', 'closingMedia']);

        return response()->json([
            'success' => true,
            'message' => 'Case retrieved successfully.',
            'data' => new AdminCaseEntryResource($case),
        ]);
    }
}
