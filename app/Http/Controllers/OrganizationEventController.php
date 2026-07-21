<?php

namespace App\Http\Controllers;

use App\Enums\TaskEvent;
use App\Models\Organization;
use App\Support\StatusEffects;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Org-owner administration of the per-event automations (see docs/event-api.md):
 * for each progress event a target status (optional), the set of currently held
 * statuses that may be overwritten, and additional field effects. The chosen
 * target status's own on-enter effects are shown read-only in the UI.
 */
class OrganizationEventController extends Controller
{
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
        $configs = $organization->eventAutomations()->get()
            ->keyBy(fn ($config) => $config->event->value);

        return view('organization.events', [
            'organization' => $organization,
            'statuses' => $statuses,
            'configs' => $configs,
            'groups' => TaskEvent::groups(),
            'effectFields' => StatusEffects::ALLOWED_FIELDS,
            // status_id => on-enter effects, for the read-only "column automations".
            'statusEffects' => $statuses->mapWithKeys(
                fn ($status) => [$status->id => $status->on_enter_effects ?? []]
            ),
        ]);
    }

    public function update(Request $request, string $event): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);

        $taskEvent = TaskEvent::tryFrom($event);
        abort_unless($taskEvent !== null, 404);

        $statusIds = $organization->statuses()->pluck('id')->all();

        $validated = $request->validate([
            'target_status_id' => ['nullable', 'integer', Rule::in($statusIds)],
            'overridable_status_ids' => ['nullable', 'array'],
            'overridable_status_ids.*' => ['integer', Rule::in($statusIds)],
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

        $overridable = array_values(array_map('intval', $validated['overridable_status_ids'] ?? []));

        $organization->eventAutomations()->updateOrCreate(
            ['event' => $taskEvent->value],
            [
                'target_status_id' => $validated['target_status_id'] ?? null,
                'overridable_status_ids' => $overridable ?: null,
                'effects' => $effects ?: null,
            ]
        );

        $organization->increment('status_config_version');

        return back()->with('status', __('events.saved', ['event' => $taskEvent->label()]));
    }
}
