<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatrollingModeResource;
use App\Models\PatrollingModes;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PatrollingModeController extends Controller
{
    public function index()
    {
        $modes = PatrollingModes::query()
            ->latest('pm_created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Patrolling modes retrieved successfully.',
            'data' => PatrollingModeResource::collection($modes),
            'meta' => [
                'current_page' => $modes->currentPage(),
                'per_page' => $modes->perPage(),
                'total' => $modes->total(),
                'last_page' => $modes->lastPage(),
            ],
        ]);
    }

    /**
     * All patrolling modes (Flutter field app dropdown).
     */
    public function forApp()
    {
        $modes = PatrollingModes::query()->orderBy('pm_mode_name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Patrolling modes retrieved successfully.',
            'data' => PatrollingModeResource::collection($modes),
        ]);
    }

    public function show(PatrollingModes $patrollingMode)
    {
        return response()->json([
            'success' => true,
            'message' => 'Patrolling mode retrieved successfully.',
            'data' => new PatrollingModeResource($patrollingMode),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pm_mode_name' => ['required', 'string', 'max:100', Rule::unique('patrolling_modes', 'pm_mode_name')],
        ]);

        $mode = PatrollingModes::create([
            'pm_mode_name' => trim($validated['pm_mode_name']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Patrolling mode created successfully.',
            'data' => new PatrollingModeResource($mode),
        ], 201);
    }

    public function update(Request $request, PatrollingModes $patrollingMode)
    {
        $validated = $request->validate([
            'pm_mode_name' => ['required', 'string', 'max:100', Rule::unique('patrolling_modes', 'pm_mode_name')->ignore($patrollingMode->pm_id, 'pm_id')],
        ]);

        $patrollingMode->pm_mode_name = trim($validated['pm_mode_name']);
        $patrollingMode->save();

        return response()->json([
            'success' => true,
            'message' => 'Patrolling mode updated successfully.',
            'data' => new PatrollingModeResource($patrollingMode->fresh()),
        ]);
    }

    public function destroy(PatrollingModes $patrollingMode)
    {
        $patrollingMode->delete();

        return response()->json([
            'success' => true,
            'message' => 'Patrolling mode deleted successfully.',
            'data' => null,
        ]);
    }
}
