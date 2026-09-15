<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Field-app version control. The app calls {@see show} on every launch
 * (unauthenticated — it must work before login and for a build that is
 * about to be blocked) and compares its own build number against
 * `min_supported_build` (below it: mandatory update, app is unusable
 * until updated) and `latest_build` (below it: optional "update
 * available" prompt). Master admins set these from the admin panel via
 * {@see index}/{@see update} after publishing a new build.
 */
class AppVersionController extends Controller
{
    public function show(Request $request)
    {
        $validated = $request->validate([
            'platform' => ['sometimes', Rule::in(AppVersion::PLATFORMS)],
        ]);

        $version = AppVersion::where('av_platform', $validated['platform'] ?? 'android')->first();
        if ($version === null) {
            // Nothing configured yet — never block an app over a missing row.
            return response()->json([
                'success' => true,
                'message' => 'No version policy configured.',
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'App version retrieved successfully.',
            'data' => $version->toPublicArray(),
        ]);
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'App versions retrieved successfully.',
            'data' => AppVersion::orderBy('av_platform')->get()->map->toPublicArray()->values(),
        ]);
    }

    public function update(Request $request, string $platform)
    {
        abort_unless(in_array($platform, AppVersion::PLATFORMS, true), 404, 'Unknown platform.');

        $validated = $request->validate([
            'latest_version' => ['required', 'string', 'max:32', 'regex:/^\d+\.\d+\.\d+$/'],
            'latest_build' => ['required', 'integer', 'min:1'],
            'min_supported_build' => ['required', 'integer', 'min:1', 'lte:latest_build'],
            'update_url' => ['nullable', 'url', 'max:500'],
            'release_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $version = AppVersion::firstOrNew(['av_platform' => $platform]);
        $version->fill([
            'av_platform' => $platform,
            'av_latest_version' => $validated['latest_version'],
            'av_latest_build' => $validated['latest_build'],
            'av_min_supported_build' => $validated['min_supported_build'],
            'av_update_url' => $validated['update_url'] ?? null,
            'av_release_notes' => $validated['release_notes'] ?? null,
            'av_updated_at' => now(),
            'av_updated_by' => $request->user()?->getKey(),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'App version updated successfully.',
            'data' => $version->toPublicArray(),
        ]);
    }
}
