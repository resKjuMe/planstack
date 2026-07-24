<?php

namespace App\Http\Controllers;

use App\Models\CustomField;
use App\Models\Organization;
use App\Support\OrganizationTabs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Org-owner administration of the organization's custom task fields. Each field
 * has a DE/EN label, a data type and an optional Laravel validation rule; its
 * value is filled per task via the API (stored in tasks.custom_fields).
 */
class OrganizationCustomFieldController extends Controller
{
    private function ownedOrganization(Request $request): Organization
    {
        $user = $request->user();
        $organization = $user->organization;

        abort_unless($organization && $organization->isOwner($user), 403);

        return $organization;
    }

    public function index(Request $request): InertiaResponse
    {
        $organization = $this->ownedOrganization($request);

        $existingKeys = $organization->customFields()->pluck('key')->all();
        $types = array_keys(CustomField::TYPES);

        return Inertia::render('OrganizationCustomFields', [
            'tabs' => OrganizationTabs::for('custom-fields'),
            'flash' => ['status' => session('status'), 'error' => session('error')],
            'fields' => $organization->customFields()->get()->map(fn ($f) => [
                'id' => $f->id,
                'key' => $f->key,
                'label' => $f->label,
                'label_en' => $f->label_en ?? '',
                'type' => $f->type,
                'validation' => $f->validation ?? '',
                'destroyUrl' => route('organization.custom-fields.destroy', $f),
            ])->values(),
            'types' => collect($types)->map(fn ($t) => ['value' => $t, 'label' => __('custom_fields.type_'.$t)])->values(),
            // Presets, die noch nicht (nach Schlüssel) angelegt sind.
            'presets' => collect(CustomField::PRESETS)
                ->reject(fn ($preset) => in_array($preset['key'], $existingKeys, true))
                ->map(fn ($preset, $id) => ['id' => $id, 'label' => $preset['label'], 'key' => $preset['key']])
                ->values(),
            'urls' => [
                'updateAll' => route('organization.custom-fields.update-all'),
                'store' => route('organization.custom-fields.store'),
                'preset' => route('organization.custom-fields.preset'),
            ],
            'strings' => [
                'title' => __('custom_fields.title'),
                'intro' => __('custom_fields.intro', ['field' => __('custom_fields.field_placeholder')]),
                'presetsLabel' => __('custom_fields.presets_label'),
                'colKey' => __('custom_fields.col_key'),
                'colLabel' => __('custom_fields.col_label'),
                'colLabelEn' => __('custom_fields.col_label_en'),
                'colType' => __('custom_fields.col_type'),
                'colValidation' => __('custom_fields.col_validation'),
                'validationPlaceholder' => __('custom_fields.validation_placeholder'),
                'noFields' => __('custom_fields.no_fields'),
                'addField' => __('custom_fields.add_field'),
                'save' => __('custom_fields.save'),
                'delete' => __('custom_fields.delete'),
                'deleteConfirm' => __('custom_fields.delete_confirm'),
            ],
        ]);
    }

    /**
     * Create a field from a predefined preset (App\Models\CustomField::PRESETS)
     * with its fixed key, type and validation. No-op if the key already exists.
     */
    public function storePreset(Request $request): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);

        $data = $request->validate([
            'preset' => ['required', Rule::in(array_keys(CustomField::PRESETS))],
        ]);

        $preset = CustomField::PRESETS[$data['preset']];

        if ($organization->customFields()->where('key', $preset['key'])->exists()) {
            return back()->with('status', __('custom_fields.preset_exists', ['label' => $preset['label']]));
        }

        $organization->customFields()->create([
            'key' => $preset['key'],
            'label' => $preset['label'],
            'label_en' => $preset['label_en'],
            'type' => $preset['type'],
            'validation' => $preset['validation'],
            'position' => (int) $organization->customFields()->max('position') + 1,
        ]);

        return back()->with('status', __('custom_fields.created', ['label' => $preset['label']]));
    }

    /**
     * Create a custom field. The machine key is derived from the label (unique
     * per org) and immutable afterwards — it is the field's API name.
     */
    public function store(Request $request): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'label_en' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(CustomField::TYPES))],
            'validation' => ['nullable', 'string', 'max:255'],
        ]);

        $base = Str::snake(Str::slug($data['label'], '_')) ?: 'field';
        $key = $base;
        $i = 1;
        while ($organization->customFields()->where('key', $key)->exists()) {
            $key = $base.'_'.(++$i);
        }

        $organization->customFields()->create([
            'key' => $key,
            'label' => $data['label'],
            'label_en' => $data['label_en'] ?? null,
            'type' => $data['type'],
            'validation' => $data['validation'] ?? null,
            'position' => (int) $organization->customFields()->max('position') + 1,
        ]);

        return back()->with('status', __('custom_fields.created', ['label' => $data['label']]));
    }

    /**
     * Bulk-save the editable attributes of every existing field (single Save
     * button). The key stays immutable.
     */
    public function updateAll(Request $request): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);

        $validated = $request->validate([
            'fields' => ['array'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.label_en' => ['nullable', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::in(array_keys(CustomField::TYPES))],
            'fields.*.validation' => ['nullable', 'string', 'max:255'],
        ]);

        $models = $organization->customFields()->get()->keyBy('id');

        DB::transaction(function () use ($validated, $models) {
            foreach ($validated['fields'] ?? [] as $id => $data) {
                $field = $models->get((int) $id);
                if ($field === null) {
                    continue;
                }
                $field->update([
                    'label' => $data['label'],
                    'label_en' => $data['label_en'] ?? null,
                    'type' => $data['type'],
                    'validation' => $data['validation'] ?? null,
                ]);
            }
        });

        return back()->with('status', __('custom_fields.saved_all'));
    }

    public function destroy(Request $request, CustomField $customField): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);
        abort_unless($customField->organization_id === $organization->id, 403);

        $customField->delete();

        return back()->with('status', __('custom_fields.deleted'));
    }
}
