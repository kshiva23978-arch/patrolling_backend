<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatrolTypeResource;
use App\Models\PatrolTypes;
use App\Models\Ranges;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PatrolTypeController extends Controller
{
    public function index()
    {
        $patrolTypes = PatrolTypes::query()
            ->latest('pt_created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Patrol types retrieved successfully.',
            'data' => PatrolTypeResource::collection($patrolTypes),
            'meta' => [
                'current_page' => $patrolTypes->currentPage(),
                'per_page' => $patrolTypes->perPage(),
                'total' => $patrolTypes->total(),
                'last_page' => $patrolTypes->lastPage(),
            ],
        ]);
    }

    /**
     * Patrol types available to the given range (Flutter field app dropdown).
     */
    public function forApp(Request $request)
    {
        $validated = $request->validate([
            'range_id' => ['required', 'uuid', 'exists:ranges,rn_id'],
        ]);

        $range = Ranges::findOrFail($validated['range_id']);

        $patrolTypes = PatrolTypes::query()
            ->where('pt_status', true)
            ->orderBy('pt_name')
            ->get()
            ->filter(fn (PatrolTypes $type) => $type->isAllowedForCategory($range->rn_category))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Patrol types retrieved successfully.',
            'data' => PatrolTypeResource::collection($patrolTypes),
        ]);
    }

    public function show(PatrolTypes $patrolType)
    {
        return response()->json([
            'success' => true,
            'message' => 'Patrol type retrieved successfully.',
            'data' => new PatrolTypeResource($patrolType),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pt_name' => ['required', 'string', 'max:255', Rule::unique('patrol_types', 'pt_name')],
            'pt_description' => ['nullable', 'string'],
            'pt_status' => ['sometimes', 'boolean'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => [Rule::in(Ranges::CATEGORIES)],
        ]);

        $patrolType = PatrolTypes::create([
            'pt_name' => trim($validated['pt_name']),
            'pt_description' => $validated['pt_description'] ?? null,
            'pt_status' => $validated['pt_status'] ?? true,
        ]);

        $this->syncCategories($patrolType, $validated['categories'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Patrol type created successfully.',
            'data' => new PatrolTypeResource($patrolType),
        ], 201);
    }

    public function update(Request $request, PatrolTypes $patrolType)
    {
        $validated = $request->validate([
            'pt_name' => ['sometimes', 'string', 'max:255', Rule::unique('patrol_types', 'pt_name')->ignore($patrolType->pt_id, 'pt_id')],
            'pt_description' => ['sometimes', 'nullable', 'string'],
            'pt_status' => ['sometimes', 'boolean'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => [Rule::in(Ranges::CATEGORIES)],
        ]);

        if (isset($validated['pt_name'])) {
            $patrolType->pt_name = trim($validated['pt_name']);
        }

        if (array_key_exists('pt_description', $validated)) {
            $patrolType->pt_description = $validated['pt_description'];
        }

        if (array_key_exists('pt_status', $validated)) {
            $patrolType->pt_status = $validated['pt_status'];
        }

        $patrolType->save();

        $this->syncCategories($patrolType, $validated['categories'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Patrol type updated successfully.',
            'data' => new PatrolTypeResource($patrolType->fresh()),
        ]);
    }

    public function destroy(PatrolTypes $patrolType)
    {
        $patrolType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Patrol type deleted successfully.',
            'data' => null,
        ]);
    }

    private function syncCategories(PatrolTypes $patrolType, ?array $categories): void
    {
        if ($categories === null) {
            return;
        }

        DB::table('patrol_type_categories')->where('ptc_patrol_type_id', $patrolType->pt_id)->delete();

        if (empty($categories)) {
            return;
        }

        DB::table('patrol_type_categories')->insert(
            array_map(fn ($category) => [
                'ptc_patrol_type_id' => $patrolType->pt_id,
                'ptc_category' => $category,
            ], array_unique($categories))
        );
    }
}
