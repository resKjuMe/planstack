<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrgStatus;
use App\Models\OrgStatusTransition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        ]);
    }

    public function update(Request $request, OrgStatus $status): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);
        abort_unless($status->organization_id === $organization->id, 403);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'label_en' => ['nullable', 'string', 'max:255'],
            'color_token' => ['required', Rule::in(self::COLORS)],
            'position' => ['required', 'integer', 'min:0'],
            'wip_limit' => ['nullable', 'integer', 'min:1'],
            'is_column' => ['sometimes', 'boolean'],
            'default_expanded' => ['sometimes', 'boolean'],
        ]);

        $status->update([
            'label' => $data['label'],
            'label_en' => $data['label_en'] ?? null,
            'color_token' => $data['color_token'],
            'position' => $data['position'],
            'wip_limit' => $data['wip_limit'] ?? null,
            'is_column' => $request->boolean('is_column'),
            'default_expanded' => $request->boolean('default_expanded'),
        ]);

        $organization->increment('status_config_version');

        return back()->with('status', __('board_admin.status_saved', ['label' => $status->label]));
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
}
