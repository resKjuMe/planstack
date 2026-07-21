<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrgStatus;
use App\Models\OrgStatusGroup;
use App\Models\OrgStatusTransition;
use App\Models\Task;
use App\Support\StatusEffects;
use App\Support\StatusIcons;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Org-owner administration of the organization's configurable task statuses:
 * edit presentation/workflow of the existing statuses (label, color, order,
 * column/lane, collapse default, WIP limit) and the allowed transitions. The
 * board reads these live (see OrgBoardWorkflow).
 *
 * Creating/deleting free-form custom statuses is intentionally deferred until
 * the status_id authority flip is complete (a custom status is not yet
 * assignable to tasks while the ENUM `status` remains the authority).
 */
class OrganizationTaskStatusController extends Controller
{
    /** Finite color-token palette; mirrors resources/js/board/statusColors.js. */
    public const COLORS = [
        'gray', 'slate', 'indigo', 'sky', 'blue', 'navy', 'purple',
        'green', 'emerald', 'teal', 'rose', 'red', 'orange', 'amber',
    ];

    private function ownedOrganization(Request $request): Organization
    {
        $user = $request->user();
        $organization = $user->organization;

        abort_unless($organization && $organization->isOwner($user), 403);

        return $organization;
    }

    public function index(Request $request): View
    {
        $organization = $this->ownedOrganization($request);

        $statuses = $organization->statuses()->get();
        // fromStatusId => [toStatusId, …] for the transitions matrix.
        $transitions = OrgStatusTransition::query()
            ->whereIn('from_status_id', $statuses->pluck('id'))
            ->get()
            ->groupBy('from_status_id')
            ->map(fn ($rows) => $rows->pluck('to_status_id')->all());

        return view('organization.statuses', [
            'organization' => $organization,
            'statuses' => $statuses,
            'transitions' => $transitions,
            'colors' => self::COLORS,
            'iconKeys' => StatusIcons::keys(),
            'iconMarkup' => StatusIcons::all(),
            'groups' => $organization->statusGroups()->get(),
        ]);
    }

    public function update(Request $request, OrgStatus $status): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);
        abort_unless($status->organization_id === $organization->id, 403);

        $groupIds = $organization->statusGroups()->pluck('id')->all();

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'label_en' => ['nullable', 'string', 'max:255'],
            'color_token' => ['required', Rule::in(self::COLORS)],
            'icon' => ['nullable', Rule::in(StatusIcons::keys())],
            'position' => ['required', 'integer', 'min:0'],
            'wip_limit' => ['nullable', 'integer', 'min:1'],
            'group_id' => ['nullable', 'integer', Rule::in($groupIds)],
            'is_column' => ['sometimes', 'boolean'],
            'default_expanded' => ['sometimes', 'boolean'],
        ]);

        $status->update([
            'label' => $data['label'],
            'label_en' => $data['label_en'] ?? null,
            'color_token' => $data['color_token'],
            'icon' => $data['icon'] ?? null,
            'position' => $data['position'],
            'wip_limit' => $data['wip_limit'] ?? null,
            'group_id' => $data['group_id'] ?? null,
            'is_column' => $request->boolean('is_column'),
            'default_expanded' => $request->boolean('default_expanded'),
        ]);

        $organization->increment('status_config_version');

        return back()->with('status', __('board_admin.status_saved', ['label' => $status->label]));
    }

    /**
     * Bulk-save every status row at once (single Save button): presentation,
     * grouping, WIP, column/expanded flags, drag order (position) and the
     * on-enter effects. Only this org's statuses are touched; unknown ids are
     * skipped. Create/delete stay separate operations.
     */
    public function updateAll(Request $request): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);
        $groupIds = $organization->statusGroups()->pluck('id')->all();

        $validated = $request->validate([
            'statuses' => ['array'],
            'statuses.*.label' => ['required', 'string', 'max:255'],
            'statuses.*.label_en' => ['nullable', 'string', 'max:255'],
            'statuses.*.color_token' => ['required', Rule::in(self::COLORS)],
            'statuses.*.icon' => ['nullable', Rule::in(StatusIcons::keys())],
            'statuses.*.position' => ['nullable', 'integer', 'min:0'],
            'statuses.*.wip_limit' => ['nullable', 'integer', 'min:1'],
            'statuses.*.group_id' => ['nullable', 'integer', Rule::in($groupIds)],
            'statuses.*.is_column' => ['sometimes'],
            'statuses.*.default_expanded' => ['sometimes'],
            'statuses.*.effects' => ['nullable', 'array'],
            'statuses.*.effects.*.field' => ['required', Rule::in(StatusEffects::ALLOWED_FIELDS)],
            'statuses.*.effects.*.value' => ['nullable', 'string', 'max:255'],
            'statuses.*.effects.*.only_if_empty' => ['sometimes'],
        ]);

        $models = $organization->statuses()->get()->keyBy('id');

        DB::transaction(function () use ($validated, $models) {
            foreach ($validated['statuses'] ?? [] as $id => $data) {
                $status = $models->get((int) $id);
                if ($status === null) {
                    continue;
                }

                $effects = [];
                foreach ($data['effects'] ?? [] as $effect) {
                    $effects[] = [
                        'field' => $effect['field'],
                        'value' => $effect['value'] ?? '',
                        'only_if_empty' => ! empty($effect['only_if_empty']),
                    ];
                }

                $status->update([
                    'label' => $data['label'],
                    'label_en' => $data['label_en'] ?? null,
                    'color_token' => $data['color_token'],
                    'icon' => $data['icon'] ?? null,
                    'position' => $data['position'] ?? $status->position,
                    'wip_limit' => $data['wip_limit'] ?? null,
                    'group_id' => $data['group_id'] ?? null,
                    'is_column' => ! empty($data['is_column']),
                    'default_expanded' => ! empty($data['default_expanded']),
                    'on_enter_effects' => $effects ?: null,
                ]);
            }
        });

        $organization->increment('status_config_version');

        return back()->with('status', __('board_admin.saved_all'));
    }

    /**
     * Rebuild the whole transitions matrix from the posted map
     * transitions[fromStatusId][] = toStatusId.
     */
    public function updateTransitions(Request $request): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);

        $statusIds = $organization->statuses()->pluck('id')->all();
        $posted = (array) $request->input('transitions', []);

        DB::transaction(function () use ($organization, $statusIds, $posted) {
            OrgStatusTransition::whereIn('from_status_id', $statusIds)->delete();

            foreach ($posted as $from => $targets) {
                $from = (int) $from;
                if (! in_array($from, $statusIds, true)) {
                    continue;
                }
                foreach ((array) $targets as $to) {
                    $to = (int) $to;
                    // Only wire transitions between this org's statuses; no self-loop.
                    if ($to !== $from && in_array($to, $statusIds, true)) {
                        OrgStatusTransition::create(['from_status_id' => $from, 'to_status_id' => $to]);
                    }
                }
            }

            $organization->increment('status_config_version');
        });

        return back()->with('status', __('board_admin.transitions_saved'));
    }

    /** Kinds a custom status may take (waiting is excluded — it is gate-derived). */
    public const CUSTOM_KINDS = ['active', 'review', 'done', 'exception'];

    /**
     * Create a custom (role-less) status. It participates as a board column (or
     * exception lane) and as a transition target, but is never the automatic
     * target of a wired action (those resolve by role).
     */
    public function storeStatus(Request $request): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);
        $groupIds = $organization->statusGroups()->pluck('id')->all();

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'label_en' => ['nullable', 'string', 'max:255'],
            'color_token' => ['required', Rule::in(self::COLORS)],
            'icon' => ['nullable', Rule::in(StatusIcons::keys())],
            'kind' => ['required', Rule::in(self::CUSTOM_KINDS)],
            'wip_limit' => ['nullable', 'integer', 'min:1'],
            'group_id' => ['nullable', 'integer', Rule::in($groupIds)],
            'is_column' => ['sometimes', 'boolean'],
            'default_expanded' => ['sometimes', 'boolean'],
        ]);

        // Unique, slug-based key per org (must not collide with canonical keys).
        $base = Str::upper(Str::slug($data['label'], '_')) ?: 'STATUS';
        $key = $base;
        $i = 1;
        while ($organization->statuses()->where('key', $key)->exists()) {
            $key = $base.'_'.(++$i);
        }

        $isDone = $data['kind'] === 'done';

        $organization->statuses()->create([
            'role' => null, // custom
            'key' => $key,
            'label' => $data['label'],
            'label_en' => $data['label_en'] ?? null,
            'kind' => $data['kind'],
            'color_token' => $data['color_token'],
            'icon' => $data['icon'] ?? null,
            'position' => (int) $organization->statuses()->max('position') + 1,
            'is_column' => $data['kind'] === 'exception' ? false : $request->boolean('is_column', true),
            'default_expanded' => $request->boolean('default_expanded'),
            'wip_limit' => $data['wip_limit'] ?? null,
            'counts_as_done' => $isDone,
            'counts_as_delivered' => $isDone,
            'group_id' => $data['group_id'] ?? null,
        ]);

        $organization->increment('status_config_version');

        return back()->with('status', __('board_admin.status_created', ['label' => $data['label']]));
    }

    /**
     * Delete a custom status. Canonical (role-bearing) statuses are protected,
     * and a status still assigned to any task cannot be removed.
     */
    public function destroyStatus(Request $request, OrgStatus $status): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);
        abort_unless($status->organization_id === $organization->id, 403);

        if ($status->role !== null) {
            return back()->withErrors(['status' => __('board_admin.cannot_delete_canonical')]);
        }

        if (Task::where('status_id', $status->id)->exists()) {
            return back()->withErrors(['status' => __('board_admin.cannot_delete_in_use')]);
        }

        $status->delete();
        $organization->increment('status_config_version');

        return back()->with('status', __('board_admin.status_deleted'));
    }

    /**
     * Replace a status's on-enter effects (automatic assignments + field
     * population). Fields are restricted to the effect allow-list.
     */
    public function updateEffects(Request $request, OrgStatus $status): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);
        abort_unless($status->organization_id === $organization->id, 403);

        $validated = $request->validate([
            'effects' => ['nullable', 'array'],
            'effects.*.field' => ['required', Rule::in(StatusEffects::ALLOWED_FIELDS)],
            'effects.*.value' => ['nullable', 'string', 'max:255'],
            'effects.*.only_if_empty' => ['sometimes'],
        ]);

        $effects = [];
        foreach ($validated['effects'] ?? [] as $effect) {
            $effects[] = [
                'field' => $effect['field'],
                'value' => $effect['value'] ?? '',
                'only_if_empty' => ! empty($effect['only_if_empty']),
            ];
        }

        $status->update(['on_enter_effects' => $effects ?: null]);
        $organization->increment('status_config_version');

        return back()->with('status', __('board_admin.effects_saved'));
    }

    /**
     * Persist a new status order from the drag-and-drop list (comma-separated
     * status ids). Only this org's statuses are (re)positioned.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);

        $ids = array_filter(array_map('intval', explode(',', (string) $request->input('order'))));
        $valid = $organization->statuses()->pluck('id')->all();

        $position = 0;
        foreach ($ids as $id) {
            if (in_array($id, $valid, true)) {
                OrgStatus::where('id', $id)
                    ->where('organization_id', $organization->id)
                    ->update(['position' => $position++]);
            }
        }

        $organization->increment('status_config_version');

        return back()->with('status', __('board_admin.order_saved'));
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
        ]);

        // Unique, slug-based key per organization.
        $base = Str::slug($data['label']) ?: 'group';
        $key = $base;
        $i = 1;
        while ($organization->statusGroups()->where('key', $key)->exists()) {
            $key = $base.'-'.(++$i);
        }

        $organization->statusGroups()->create([
            'key' => $key,
            'label' => $data['label'],
            'position' => (int) $organization->statusGroups()->max('position') + 1,
        ]);

        $organization->increment('status_config_version');

        return back()->with('status', __('board_admin.group_saved', ['label' => $data['label']]));
    }

    public function destroyGroup(Request $request, OrgStatusGroup $group): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);
        abort_unless($group->organization_id === $organization->id, 403);

        // Statuses keep existing; their group_id is set null via the FK.
        $group->delete();
        $organization->increment('status_config_version');

        return back()->with('status', __('board_admin.group_deleted'));
    }
}
