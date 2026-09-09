<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WasteCategoryResource;
use App\Models\WasteCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WasteCategoriesController extends Controller
{
    public function index()
    {
        $categories = WasteCategory::query()->latest('wc_created_at')->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Waste categories retrieved successfully.',
            'data' => WasteCategoryResource::collection($categories),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'last_page' => $categories->lastPage(),
            ],
        ]);
    }

    /** Active waste categories (Flutter field app dropdown when logging a cleanup). */
    public function forApp()
    {
        $categories = WasteCategory::where('wc_status', true)->orderBy('wc_name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Waste categories retrieved successfully.',
            'data' => WasteCategoryResource::collection($categories),
        ]);
    }

    public function show(WasteCategory $wasteCategory)
    {
        return response()->json([
            'success' => true,
            'message' => 'Waste category retrieved successfully.',
            'data' => new WasteCategoryResource($wasteCategory),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('waste_categories', 'wc_name')],
            'status' => ['sometimes', 'boolean'],
        ]);

        $category = WasteCategory::create([
            'wc_name' => trim($validated['name']),
            'wc_status' => $validated['status'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Waste category created successfully.',
            'data' => new WasteCategoryResource($category),
        ], 201);
    }

    public function update(Request $request, WasteCategory $wasteCategory)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('waste_categories', 'wc_name')->ignore($wasteCategory->wc_id, 'wc_id')],
            'status' => ['sometimes', 'boolean'],
        ]);

        $wasteCategory->wc_name = trim($validated['name']);
        if (array_key_exists('status', $validated)) {
            $wasteCategory->wc_status = $validated['status'];
        }
        $wasteCategory->save();

        return response()->json([
            'success' => true,
            'message' => 'Waste category updated successfully.',
            'data' => new WasteCategoryResource($wasteCategory->fresh()),
        ]);
    }

    public function destroy(WasteCategory $wasteCategory)
    {
        return $this->deleteOrConflict($wasteCategory, 'waste category');
    }
}
