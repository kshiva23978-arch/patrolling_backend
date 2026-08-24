<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RangeCustomFieldResource;
use App\Models\RangeCustomField;
use App\Models\Ranges;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin-managed per-range custom fields, dynamically shown on the field
 * app's "Patrol Report" form for that range — see {@see RangeCustomField}.
 */
class RangeCustomFieldController extends Controller
{
    /**
     * Every custom field for a range, including disabled ones — the admin
     * panel's management screen needs to show and re-enable those too.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'range_id' => ['required', 'uuid', 'exists:ranges,rn_id'],
        ]);

        $fields = RangeCustomField::where('rcf_range_id', $validated['range_id'])
            ->orderBy('rcf_sort_order')
            ->orderBy('rcf_created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Custom fields retrieved successfully.',
            'data' => RangeCustomFieldResource::collection($fields),
        ]);
    }

    /**
     * Active custom fields for a range, ordered for display — what the
     * field app fetches once a range is selected on a patrol entry.
     */
    public function forApp(Request $request)
    {
        $validated = $request->validate([
            'range_id' => ['required', 'uuid', 'exists:ranges,rn_id'],
        ]);

        $fields = RangeCustomField::where('rcf_range_id', $validated['range_id'])
            ->where('rcf_is_active', true)
            ->orderBy('rcf_sort_order')
            ->orderBy('rcf_created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Custom fields retrieved successfully.',
            'data' => RangeCustomFieldResource::collection($fields),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'range_id' => ['required', 'uuid', 'exists:ranges,rn_id'],
        ]);

        $range = Ranges::findOrFail($request->input('range_id'));
        $validated = $this->validatePayload($request);

        $field = RangeCustomField::create([
            'rcf_range_id' => $range->rn_id,
            'rcf_field_name' => trim($validated['field_name']),
            'rcf_field_key' => $this->generateFieldKey($range, trim($validated['field_name'])),
            'rcf_input_type' => $validated['input_type'],
            'rcf_options' => $validated['input_type'] === RangeCustomField::TYPE_DROPDOWN
                ? $validated['options']
                : null,
            'rcf_is_required' => $validated['is_required'] ?? false,
            'rcf_is_active' => $validated['is_active'] ?? true,
            'rcf_sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Custom field created successfully.',
            'data' => new RangeCustomFieldResource($field),
        ], 201);
    }

    public function update(Request $request, RangeCustomField $customField)
    {
        $validated = $this->validatePayload($request, $customField);

        if (isset($validated['field_name'])) {
            $customField->rcf_field_name = trim($validated['field_name']);
        }

        if (isset($validated['input_type'])) {
            $customField->rcf_input_type = $validated['input_type'];
        }

        if ($customField->rcf_input_type === RangeCustomField::TYPE_DROPDOWN) {
            if (isset($validated['options'])) {
                $customField->rcf_options = $validated['options'];
            }
        } else {
            $customField->rcf_options = null;
        }

        if (array_key_exists('is_required', $validated)) {
            $customField->rcf_is_required = $validated['is_required'];
        }

        if (array_key_exists('is_active', $validated)) {
            $customField->rcf_is_active = $validated['is_active'];
        }

        if (array_key_exists('sort_order', $validated)) {
            $customField->rcf_sort_order = $validated['sort_order'];
        }

        $customField->save();

        return response()->json([
            'success' => true,
            'message' => 'Custom field updated successfully.',
            'data' => new RangeCustomFieldResource($customField->fresh()),
        ]);
    }

    public function destroy(RangeCustomField $customField)
    {
        return $this->deleteOrConflict($customField, 'custom field');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?RangeCustomField $existing = null): array
    {
        $creating = $existing === null;

        $validated = $request->validate([
            'field_name' => [$creating ? 'required' : 'sometimes', 'string', 'max:150'],
            'input_type' => [$creating ? 'required' : 'sometimes', Rule::in(RangeCustomField::INPUT_TYPES)],
            'options' => ['sometimes', 'array', 'min:1'],
            'options.*' => ['string', 'max:150'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $inputType = $validated['input_type'] ?? $existing?->rcf_input_type;

        if ($inputType === RangeCustomField::TYPE_DROPDOWN && empty($validated['options']) && ($creating || ($existing->rcf_options ?? []) === [])) {
            throw ValidationException::withMessages([
                'options' => 'Add at least one option for a dropdown field.',
            ]);
        }

        return $validated;
    }

    /**
     * Slugifies the field name into a stable key (e.g. "Type of Conflict" →
     * `type_of_conflict`), deduping against existing keys on the same range
     * the same way {@see PatrolEntryController::generatePatrolId} does.
     */
    private function generateFieldKey(Ranges $range, string $fieldName): string
    {
        $base = Str::slug($fieldName, '_') ?: 'field';
        $candidate = $base;
        $suffix = 1;

        while (RangeCustomField::where('rcf_range_id', $range->rn_id)->where('rcf_field_key', $candidate)->exists()) {
            $suffix++;
            $candidate = "{$base}_{$suffix}";
        }

        return $candidate;
    }
}
