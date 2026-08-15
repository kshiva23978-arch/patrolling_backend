<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Designations;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DesignationsController extends Controller
{
    public function index()
    {
        $designations = Designations::query()
            ->select([
                'd_id',
                'd_designation_name',
                'd_rank_order',
                'd_description',
                'd_status',
                'd_created_at',
                'd_updated_at',
            ])
            ->latest('d_created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Designations retrieved successfully.',
            'data' => \App\Http\Resources\DesignationResource::collection($designations),
            'meta' => [
                'current_page' => $designations->currentPage(),
                'per_page' => $designations->perPage(),
                'total' => $designations->total(),
                'last_page' => $designations->lastPage(),
            ],
        ]);
    }

    public function show(Designations $designation)
    {
        return response()->json([
            'success' => true,
            'message' => 'Designation retrieved successfully.',
            'data' => new \App\Http\Resources\DesignationResource($designation),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'd_designation_name' => ['required', 'string', 'max:255', Rule::unique('designations', 'd_designation_name')],
            'd_rank_order' => ['required', 'integer', 'min:1'],
            'd_description' => ['nullable', 'string'],
            'd_status' => ['required', 'boolean'],
        ]);

        $designation = Designations::create([
            'd_designation_name' => trim($validated['d_designation_name']),
            'd_rank_order' => $validated['d_rank_order'],
            'd_description' => $validated['d_description'] ?? null,
            'd_status' => $validated['d_status'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Designation created successfully.',
            'data' => new \App\Http\Resources\DesignationResource($designation),
        ], 201);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'd_id' => ['required', 'string'],
            'd_designation_name' => ['sometimes', 'string', 'max:255'],
            'd_rank_order' => ['sometimes', 'integer', 'min:1'],
            'd_description' => ['sometimes', 'string'],
            'd_status' => ['sometimes', 'boolean'],
        ]);

        $designation = Designations::where('d_id', $validated['d_id'])->firstOrFail();

        if (isset($validated['d_designation_name'])) {
            $designation->d_designation_name = trim($validated['d_designation_name']);
        }

        if (array_key_exists('d_rank_order', $validated)) {
            $designation->d_rank_order = $validated['d_rank_order'];
        }

        if (array_key_exists('d_description', $validated)) {
            $designation->d_description = $validated['d_description'];
        }

        if (array_key_exists('d_status', $validated)) {
            $designation->d_status = $validated['d_status'];
        }

        $designation->save();

        return response()->json([
            'success' => true,
            'message' => 'Designation updated successfully.',
            'data' => new \App\Http\Resources\DesignationResource($designation->fresh()),
        ]);
    }

    public function destroy(Designations $designation)
    {
        $designation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Designation deleted successfully.',
            'data' => null,
        ]);
    }
}
