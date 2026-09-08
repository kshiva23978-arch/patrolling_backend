<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityReportFieldGroupResource;
use App\Http\Resources\ActivityReportFieldResource;
use App\Models\Activity;
use App\Models\ActivityReportField;
use App\Models\ActivityReportFieldGroup;
use App\Models\ActivityReportFieldValue;
use App\Models\ActivityReportGroupEntry;
use App\Services\PatrolPhotoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Field-app side of an activity's dynamic, category-defined report — the
 * admin-managed field/group definitions live under
 * {@see AdminActivityReportFieldsController}; this fills them in for one
 * specific activity, before or after it's ended (a ranger who skipped an
 * optional field, or a whole repeatable group, isn't locked out once it's
 * over — same reasoning as {@see ActivityController::updateReport}). See
 * also {@see ActivityController::end}, which still requires every
 * *required* field to be answered before the activity can be closed out in
 * the first place.
 */
class ActivityReportController extends Controller
{
    public function __construct(private readonly PatrolPhotoService $photos) {}

    /**
     * The activity's category's active groups/fields, plus whatever's
     * already been answered — lets the app render the report form and
     * resume it if the ranger backgrounds the app mid-way.
     */
    public function show(Request $request, Activity $activity)
    {
        $this->authorizeOwner($request, $activity);

        if ($activity->act_category_id === null) {
            return response()->json([
                'success' => true,
                'message' => 'This activity has no category, so no report fields apply.',
                'data' => ['groups' => [], 'fields' => [], 'entries' => [], 'values' => []],
            ]);
        }

        $groups = ActivityReportFieldGroup::where('arfg_category_id', $activity->act_category_id)
            ->with(['fields' => fn ($q) => $q->where('arf_is_active', true)->orderBy('arf_sort_order')])
            ->orderBy('arfg_sort_order')
            ->get();

        $fields = ActivityReportField::where('arf_category_id', $activity->act_category_id)
            ->whereNull('arf_group_id')
            ->where('arf_is_active', true)
            ->orderBy('arf_sort_order')
            ->get();

        $entries = $activity->reportGroupEntries()->orderBy('arge_created_at')->get();
        $values = $activity->reportFieldValues()->get();

        return response()->json([
            'success' => true,
            'message' => 'Report fields retrieved successfully.',
            'data' => [
                'groups' => ActivityReportFieldGroupResource::collection($groups),
                'fields' => ActivityReportFieldResource::collection($fields),
                'entries' => $entries->map(fn (ActivityReportGroupEntry $e) => [
                    'id' => $e->arge_id,
                    'group_id' => $e->arge_group_id,
                ]),
                'values' => $values->map(fn (ActivityReportFieldValue $v) => [
                    'field_id' => $v->arfv_field_id,
                    'group_entry_id' => $v->arfv_group_entry_id,
                    'value' => $v->arfv_value,
                    'photo_url' => $v->arfv_file_path
                        ? route('app.activity-report-photo', $v->arfv_id)
                        : null,
                ]),
            ],
        ]);
    }

    /** Adds one entry to a repeatable group (e.g. one more garbage-type row). */
    public function storeEntry(Request $request, Activity $activity)
    {
        $this->authorizeOwner($request, $activity);

        $validated = $request->validate([
            'group_id' => ['required', 'uuid', 'exists:activity_report_field_groups,arfg_id'],
            'entry_id' => ['nullable', 'uuid'],
        ]);

        $group = $this->resolveGroupForActivity($activity, $validated['group_id']);

        $entry = ActivityReportGroupEntry::create([
            'arge_id' => $validated['entry_id'] ?? null,
            'arge_activity_id' => $activity->act_id,
            'arge_group_id' => $group->arfg_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Entry added successfully.',
            'data' => ['id' => $entry->arge_id, 'group_id' => $entry->arge_group_id],
        ], 201);
    }

    /** Removes one group entry — its field values cascade with it. */
    public function destroyEntry(Request $request, Activity $activity, ActivityReportGroupEntry $entry)
    {
        $this->authorizeOwner($request, $activity);

        if ($entry->arge_activity_id !== $activity->act_id) {
            abort(404);
        }

        $entry->delete();

        return response()->json([
            'success' => true,
            'message' => 'Entry removed successfully.',
            'data' => null,
        ]);
    }

    /** Upserts one non-photo field answer (flat, or one group entry's). */
    public function putValue(Request $request, Activity $activity)
    {
        $this->authorizeOwner($request, $activity);

        $validated = $request->validate([
            'field_id' => ['required', 'uuid'],
            'group_entry_id' => ['nullable', 'uuid'],
            'value' => ['nullable', 'string', 'max:2000'],
        ]);

        $field = $this->resolveFieldForValue($activity, $validated['field_id'], $validated['group_entry_id'] ?? null);

        if ($field->arf_input_type === ActivityReportField::TYPE_PHOTO) {
            throw ValidationException::withMessages(['value' => 'Upload a photo for this field instead.']);
        }

        if (
            $field->arf_input_type === ActivityReportField::TYPE_DROPDOWN
            && $validated['value'] !== null
            && ! in_array($validated['value'], $field->arf_options ?? [], true)
        ) {
            throw ValidationException::withMessages([
                'value' => "\"{$validated['value']}\" is not a valid option for \"{$field->arf_field_name}\".",
            ]);
        }

        $value = ActivityReportFieldValue::updateOrCreate(
            [
                'arfv_activity_id' => $activity->act_id,
                'arfv_field_id' => $field->arf_id,
                'arfv_group_entry_id' => $validated['group_entry_id'] ?? null,
            ],
            ['arfv_value' => $validated['value'] ?? null, 'arfv_file_disk' => null, 'arfv_file_path' => null],
        );

        return response()->json([
            'success' => true,
            'message' => 'Answer saved successfully.',
            'data' => ['field_id' => $value->arfv_field_id, 'group_entry_id' => $value->arfv_group_entry_id, 'value' => $value->arfv_value],
        ]);
    }

    /** Uploads/replaces one `photo`-type field's answer. */
    public function putPhotoValue(Request $request, Activity $activity)
    {
        $this->authorizeOwner($request, $activity);

        $validated = $request->validate([
            'field_id' => ['required', 'uuid'],
            'group_entry_id' => ['nullable', 'uuid'],
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:15360'],
        ]);

        $field = $this->resolveFieldForValue($activity, $validated['field_id'], $validated['group_entry_id'] ?? null);

        if ($field->arf_input_type !== ActivityReportField::TYPE_PHOTO) {
            throw ValidationException::withMessages(['photo' => "\"{$field->arf_field_name}\" doesn't accept a photo."]);
        }

        $existing = ActivityReportFieldValue::where('arfv_activity_id', $activity->act_id)
            ->where('arfv_field_id', $field->arf_id)
            ->where('arfv_group_entry_id', $validated['group_entry_id'] ?? null)
            ->first();

        $stored = $this->photos->compressAndStore($request->file('photo'), 'activity-report-media/'.$activity->act_id);

        if ($existing?->arfv_file_path) {
            Storage::disk($existing->arfv_file_disk ?? 'local')->delete($existing->arfv_file_path);
        }

        $value = ActivityReportFieldValue::updateOrCreate(
            [
                'arfv_activity_id' => $activity->act_id,
                'arfv_field_id' => $field->arf_id,
                'arfv_group_entry_id' => $validated['group_entry_id'] ?? null,
            ],
            ['arfv_value' => null, 'arfv_file_disk' => 'local', 'arfv_file_path' => $stored['path']],
        );

        return response()->json([
            'success' => true,
            'message' => 'Photo saved successfully.',
            'data' => [
                'field_id' => $value->arfv_field_id,
                'group_entry_id' => $value->arfv_group_entry_id,
                'photo_url' => route('app.activity-report-photo', $value->arfv_id),
            ],
        ]);
    }

    /** Streams one report photo answer — ownership enforced via the value's activity. */
    public function photo(Request $request, ActivityReportFieldValue $value)
    {
        $this->authorizeOwner($request, $value->activity);

        if (! $value->arfv_file_path) {
            abort(404);
        }

        return Storage::disk($value->arfv_file_disk ?? 'local')->response($value->arfv_file_path);
    }

    private function resolveGroupForActivity(Activity $activity, string $groupId)
    {
        $group = ActivityReportFieldGroup::where('arfg_id', $groupId)
            ->where('arfg_category_id', $activity->act_category_id)
            ->first();

        if (! $group) {
            abort(404, 'This report group does not belong to this activity\'s category.');
        }

        return $group;
    }

    private function resolveFieldForValue(Activity $activity, string $fieldId, ?string $groupEntryId): ActivityReportField
    {
        $field = ActivityReportField::where('arf_id', $fieldId)
            ->where('arf_category_id', $activity->act_category_id)
            ->where('arf_is_active', true)
            ->first();

        if (! $field) {
            abort(404, 'This report field does not belong to this activity\'s category.');
        }

        if (($field->arf_group_id !== null) !== ($groupEntryId !== null)) {
            abort(422, 'This field and the group entry it was submitted for do not match.');
        }

        if ($groupEntryId !== null) {
            $entry = ActivityReportGroupEntry::where('arge_id', $groupEntryId)
                ->where('arge_activity_id', $activity->act_id)
                ->where('arge_group_id', $field->arf_group_id)
                ->first();

            if (! $entry) {
                abort(404, 'This entry does not belong to this activity or this field\'s group.');
            }
        }

        return $field;
    }

    private function authorizeOwner(Request $request, Activity $activity): void
    {
        if ($activity->act_created_by !== $request->user()->u_id) {
            abort(403, 'You are not the owner of this activity.');
        }
    }
}
