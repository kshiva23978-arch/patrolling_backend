<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityCategories;

class ActivityCategoriesController extends Controller
{
    /**
     * Every activity category (Flutter field app dropdown when creating an
     * activity) — whichever one the ranger picks decides whether ending the
     * activity later shows the dynamic report-fields form.
     */
    public function forApp()
    {
        $categories = ActivityCategories::query()->orderBy('ac_name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Activity categories retrieved successfully.',
            'data' => $categories->map(fn (ActivityCategories $c) => [
                'id' => $c->ac_id,
                'name' => $c->ac_name,
                'description' => $c->ac_description,
                'has_report' => $c->ac_has_report,
            ]),
        ]);
    }
}
