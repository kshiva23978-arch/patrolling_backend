<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityCategoryResource;
use App\Models\ActivityCategories;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminActivityCategoriesController extends Controller
{
    public function index()
    {
        $categories = ActivityCategories::query()
            ->with('createdBy')
            ->latest('created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Activity categories retrieved successfully.',
            'data' => ActivityCategoryResource::collection($categories),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'last_page' => $categories->lastPage(),
            ],
        ]);
    }

    public function show(ActivityCategories $activityCategory)
    {
        $activityCategory->load('createdBy');

        return response()->json([
            'success' => true,
            'message' => 'Activity category retrieved successfully.',
            'data' => new ActivityCategoryResource($activityCategory),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('activity_categories', 'ac_name')],
            'description' => ['nullable', 'string', 'max:2000'],
            'has_report' => ['sometimes', 'boolean'],
        ]);

        $category = ActivityCategories::create([
            'ac_name' => trim($validated['name']),
            'ac_description' => $validated['description'] ?? null,
            'ac_has_report' => $validated['has_report'] ?? false,
            'ac_created_by' => $request->user()->a_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Activity category created successfully.',
            'data' => new ActivityCategoryResource($category->load('createdBy')),
        ], 201);
    }

    public function update(Request $request, ActivityCategories $activityCategory)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('activity_categories', 'ac_name')->ignore($activityCategory->ac_id, 'ac_id')],
            'description' => ['nullable', 'string', 'max:2000'],
            'has_report' => ['sometimes', 'boolean'],
        ]);

        $activityCategory->ac_name = trim($validated['name']);
        $activityCategory->ac_description = $validated['description'] ?? null;
        $activityCategory->ac_has_report = $validated['has_report'] ?? false;
        $activityCategory->save();

        return response()->json([
            'success' => true,
            'message' => 'Activity category updated successfully.',
            'data' => new ActivityCategoryResource($activityCategory->fresh()->load('createdBy')),
        ]);
    }

    public function destroy(ActivityCategories $activityCategory)
    {
        return $this->deleteOrConflict($activityCategory, 'activity category');
    }
}
