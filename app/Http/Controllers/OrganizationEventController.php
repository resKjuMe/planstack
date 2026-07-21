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

        return view('organization.events', [
            'organization' => $organization,
            'statuses' => $statuses,
            'configs' => $this->configs($organization),
            'groups' => TaskEvent::groups(),
            // status_id => on-enter effects, for the read-only "column automations".
            'statusEffects' => $statuses->mapWithKeys(
                fn ($status) => [$status->id => $status->on_enter_effects ?? []]
            ),
        ]);
    }

    /**
     * Bulk-save the status routing (target + overridable statuses) for every
     * event in one request. The per-event field effects are left untouched –
     * they live on their own sub-page (see effects()).
     */
    public function update(Request $request): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);

        $statusIds = $organization->statuses()->pluck('id')->all();

        $validated = $request->validate([
            'events' => ['array'],
            'events.*.target_status_id' => ['nullable', 'integer', Rule::in($statusIds)],
            'events.*.overridable_status_ids' => ['nullable', 'array'],
            'events.*.overridable_status_ids.*' => ['integer', Rule::in($statusIds)],
        ]);

        $existing = $this->configs($organization);

        foreach ($validated['events'] ?? [] as $eventValue => $data) {
            $taskEvent = TaskEvent::tryFrom((string) $eventValue);
            if ($taskEvent === null) {
                continue;
            }

            $target = $data['target_status_id'] ?? null;
            $overridable = array_values(array_map('intval', $data['overridable_status_ids'] ?? []));

            // Nichts konfiguriert und keine bestehende Zeile ⇒ keine leere Zeile anlegen.
            if ($target === null && $overridable === [] && ! $existing->has($taskEvent->value)) {
                continue;
            }

            $organization->eventAutomations()->updateOrCreate(
                ['event' => $taskEvent->value],
                [
                    'target_status_id' => $target,
                    'overridable_status_ids' => $overridable ?: null,
                ]
            );
        }

        $organization->increment('status_config_version');

        return back()->with('status', __('events.saved_all'));
    }

    public function effects(Request $request): View
    {
        $organization = $this->ownedOrganization($request);

        return view('organization.events-effects', [
            'organization' => $organization,
            'configs' => $this->configs($organization),
            'groups' => TaskEvent::groups(),
            'effectFields' => StatusEffects::ALLOWED_FIELDS,
        ]);
    }

    /**
     * Bulk-save the additional field effects for every event. The status
     * routing configured on the main page is left untouched.
     */
    public function updateEffects(Request $request): RedirectResponse
    {
        $organization = $this->ownedOrganization($request);

        $validated = $request->validate([
            'events' => ['array'],
            'events.*.effects' => ['nullable', 'array'],
            'events.*.effects.*.field' => ['required', Rule::in(StatusEffects::ALLOWED_FIELDS)],
            'events.*.effects.*.value' => ['nullable', 'string', 'max:255'],
            'events.*.effects.*.only_if_empty' => ['sometimes'],
        ]);

        $existing = $this->configs($organization);

        foreach ($validated['events'] ?? [] as $eventValue => $data) {
            $taskEvent = TaskEvent::tryFrom((string) $eventValue);
            if ($taskEvent === null) {
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

            if ($effects === [] && ! $existing->has($taskEvent->value)) {
                continue;
            }

            $organization->eventAutomations()->updateOrCreate(
                ['event' => $taskEvent->value],
                ['effects' => $effects ?: null]
            );
        }

        $organization->increment('status_config_version');

        return back()->with('status', __('events.effects_saved'));
    }

    /**
     * @return \Illuminate\Support\Collection<string, \App\Models\OrgEventAutomation>
     */
    private function configs(Organization $organization)
    {
        return $organization->eventAutomations()->get()
            ->keyBy(fn ($config) => $config->event->value);
    }
}
