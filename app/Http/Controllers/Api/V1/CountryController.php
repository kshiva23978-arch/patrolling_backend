<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Models\Countries;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CountryController extends Controller
{
    public function index()
    {
        $countries = Countries::query()
            ->latest('co_created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Countries retrieved successfully.',
            'data' => CountryResource::collection($countries),
            'meta' => [
                'current_page' => $countries->currentPage(),
                'per_page' => $countries->perPage(),
                'total' => $countries->total(),
                'last_page' => $countries->lastPage(),
            ],
        ]);
    }

    /** Every country (Flutter field app dropdown, e.g. beach cleaning's country-wise segregation step). */
    public function forApp()
    {
        $countries = Countries::query()->orderBy('co_country_name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Countries retrieved successfully.',
            'data' => CountryResource::collection($countries),
        ]);
    }

    public function show(Countries $country)
    {
        return response()->json([
            'success' => true,
            'message' => 'Country retrieved successfully.',
            'data' => new CountryResource($country),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'co_country_name' => ['required', 'string', 'max:100', Rule::unique('countries', 'co_country_name')],
        ]);

        $country = Countries::create([
            'co_country_name' => trim($validated['co_country_name']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Country created successfully.',
            'data' => new CountryResource($country),
        ], 201);
    }

    public function update(Request $request, Countries $country)
    {
        $validated = $request->validate([
            'co_country_name' => ['required', 'string', 'max:100', Rule::unique('countries', 'co_country_name')->ignore($country->co_id, 'co_id')],
        ]);

        $country->co_country_name = trim($validated['co_country_name']);
        $country->save();

        return response()->json([
            'success' => true,
            'message' => 'Country updated successfully.',
            'data' => new CountryResource($country->fresh()),
        ]);
    }

    public function destroy(Countries $country)
    {
        return $this->deleteOrConflict($country, 'country');
    }
}
