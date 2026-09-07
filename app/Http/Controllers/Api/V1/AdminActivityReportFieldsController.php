<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityReportFieldGroupResource;
use App\Http\Resources\ActivityReportFieldResource;
use App\Models\ActivityCategories;
use App\Models\ActivityReportField;
use App\Models\ActivityReportFieldGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin-managed report fields for an activity category — shown dynamically
 * on that category's activity report form once `has_report` is on. A field
 * is either flat (`group_id` null, one value per report) or belongs to a
 * repeatable {@see ActivityReportFieldGroup} (the ranger adds as many
 * entries of that group as needed, e.g. one per garbage type collected).
 */
class AdminActivityReportFieldsController extends Controller
{
    /**
     * Every group (with its fields) and every flat field for a category,
     * including disabled ones — the admin management screen needs to show
     * and re-enable those too.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'uuid', 'exists:activity_categories,ac_id'],
        ]);

        $categoryId = $validated['category_id'];

        $groups = ActivityReportFieldGroup::where('arfg_category_id', $categoryId)
            ->with(['fields' => fn ($q) => $q->orderBy('arf_sort_order')->orderBy('arf_created_at')])
            ->orderBy('arfg_sort_order')
            ->orderBy('arfg_created_at')
            ->get();

        $fields = ActivityReportField::where('arf_category_id', $categoryId)
            ->whereNull('arf_group_id')
            ->orderBy('arf_sort_order')
            ->orderBy('arf_created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Report fields retrieved successfully.',
            'data' => [
                'groups' => ActivityReportFieldGroupResource::collection($groups),
                'fields' => ActivityReportFieldResource::collection($fields),
            ],
        ]);
    }

    public function storeGroup(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'uuid', 'exists:activity_categories,ac_id'],
            'name' => ['required', 'string', 'max:150'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $category = ActivityCategories::findOrFail($validated['category_id']);

        $group = ActivityReportFieldGroup::create([
            'arfg_category_id' => $category->ac_id,
            'arfg_name' => trim($validated['name']),
            'arfg_key' => $this->generateGroupKey($category, trim($validated['name'])),
            'arfg_sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report field group created successfully.',
            'data' => new ActivityReportFieldGroupResource($group->load('fields')),
        ], 201);
    }

    public function updateGroup(Request $request, ActivityReportFieldGroup $group)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $group->arfg_name = trim($validated['name']);
        if (array_key_exists('sort_order', $validated)) {
            $group->arfg_sort_order = $validated['sort_order'];
        }
        $group->save();

        return response()->json([
            'success' => true,
            'message' => 'Report field group updated successfully.',
            'data' => new ActivityReportFieldGroupResource($group->fresh()->load('fields')),
        ]);
    }

    /** Deleting a group cascades to its fields (see the migration's `cascadeOnDelete`). */
    public function destroyGroup(ActivityReportFieldGroup $group)
    {
        return $this->deleteOrConflict($group, 'report field group');
    }

    public function storeField(Request $request)
    {
        $request->validate([
            'category_id' => ['required', 'uuid', 'exists:activity_categories,ac_id'],
            'group_id' => ['nullable', 'uuid', 'exists:activity_report_field_groups,arfg_id'],
        ]);

        $category = ActivityCategories::findOrFail($request->input('category_id'));
        $groupId = $request->input('group_id') ?: null;

        $validated = $this->validatePayload($request);

        $field = ActivityReportField::create([
            'arf_category_id' => $category->ac_id,
            'arf_group_id' => $groupId,
            'arf_field_name' => trim($validated['field_name']),
            'arf_field_key' => $this->generateFieldKey($category->ac_id, $groupId, trim($validated['field_name'])),
            'arf_input_type' => $validated['input_type'],
            'arf_options' => $validated['input_type'] === ActivityReportField::TYPE_DROPDOWN
                ? $validated['options']
                : null,
            'arf_is_required' => $validated['is_required'] ?? false,
            'arf_is_active' => $validated['is_active'] ?? true,
            'arf_sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report field created successfully.',
            'data' => new ActivityReportFieldResource($field),
        ], 201);
    }

    public function updateField(Request $request, ActivityReportField $field)
    {
        $validated = $this->validatePayload($request, $field);

        if (isset($validated['field_name'])) {
            $field->arf_field_name = trim($validated['field_name']);
        }

        if (isset($validated['input_type'])) {
            $field->arf_input_type = $validated['input_type'];
        }

        if ($field->arf_input_type === ActivityReportField::TYPE_DROPDOWN) {
            if (isset($validated['options'])) {
                $field->arf_options = $validated['options'];
            }
        } else {
            $field->arf_options = null;
        }

        if (array_key_exists('is_required', $validated)) {
            $field->arf_is_required = $validated['is_required'];
        }

        if (array_key_exists('is_active', $validated)) {
            $field->arf_is_active = $validated['is_active'];
        }

        if (array_key_exists('sort_order', $validated)) {
            $field->arf_sort_order = $validated['sort_order'];
        }

        $field->save();

        return response()->json([
            'success' => true,
            'message' => 'Report field updated successfully.',
            'data' => new ActivityReportFieldResource($field->fresh()),
        ]);
    }

    public function destroyField(ActivityReportField $field)
    {
        return $this->deleteOrConflict($field, 'report field');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?ActivityReportField $existing = null): array
    {
        $creating = $existing === null;

        $validated = $request->validate([
            'field_name' => [$creating ? 'required' : 'sometimes', 'string', 'max:150'],
            'input_type' => [$creating ? 'required' : 'sometimes', Rule::in(ActivityReportField::INPUT_TYPES)],
            'options' => ['sometimes', 'array', 'min:1'],
            'options.*' => ['string', 'max:150'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $inputType = $validated['input_type'] ?? $existing?->arf_input_type;

        if ($inputType === ActivityReportField::TYPE_DROPDOWN && empty($validated['options']) && ($creating || ($existing->arf_options ?? []) === [])) {
            throw ValidationException::withMessages([
                'options' => 'Add at least one option for a dropdown field.',
            ]);
        }

        return $validated;
    }

    /** Slugifies the group name into a stable key, deduping against existing keys on the same category. */
    private function generateGroupKey(ActivityCategories $category, string $name): string
    {
        $base = Str::slug($name, '_') ?: 'group';
        $candidate = $base;
        $suffix = 1;

        while (ActivityReportFieldGroup::where('arfg_category_id', $category->ac_id)->where('arfg_key', $candidate)->exists()) {
            $suffix++;
            $candidate = "{$base}_{$suffix}";
        }

        return $candidate;
    }

    /** Slugifies the field name into a stable key, deduping within the same category+group (see {@see RangeCustomFieldController::generateFieldKey}). */
    private function generateFieldKey(string $categoryId, ?string $groupId, string $fieldName): string
    {
        $base = Str::slug($fieldName, '_') ?: 'field';
        $candidate = $base;
        $suffix = 1;

        while (
            ActivityReportField::where('arf_category_id', $categoryId)
                ->where('arf_group_id', $groupId)
                ->where('arf_field_key', $candidate)
                ->exists()
        ) {
            $suffix++;
            $candidate = "{$base}_{$suffix}";
        }

        return $candidate;
    }
}
