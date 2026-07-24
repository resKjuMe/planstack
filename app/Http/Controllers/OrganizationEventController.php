<?php

namespace App\Http\Controllers;

use App\Enums\TaskEvent;
use App\Models\Organization;
use App\Support\OrganizationTabs;
use App\Support\StatusEffects;
use App\Support\StatusIcons;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

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

    public function index(Request $request): InertiaResponse
    {
        $organization = $this->ownedOrganization($request);

        $statuses = $organization->statuses()->get()->map(fn ($status) => [
            'id' => $status->id,
            'label' => $status->label,
            'icon' => StatusIcons::svg($status->icon),
        ])->values();

        $groups = array_map(fn (string $group) => [
            'title' => __('events.group_'.$group),
            'events' => array_map(fn (TaskEvent $event) => [
                'value' => $event->value,
                'label' => $event->label(),
            ], TaskEvent::forGroup($group)),
        ], TaskEvent::groups());

        $configs = $this->configs($organization)->map(fn ($config) => [
            'targetStatusId' => $config->target_status_id,
            'overridableStatusIds' => array_values(array_map('intval', $config->overridable_status_ids ?? [])),
        ]);

        return Inertia::render('OrganizationEvents', [
            'tabs' => OrganizationTabs::for('events'),
            'flash' => [
                'status' => $request->session()->get('status'),
                'error' => $request->session()->get('error'),
            ],
            'statuses' => $statuses,
            'groups' => $groups,
            'configs' => (object) $configs->all(),
            'urls' => [
                'update' => route('organization.events.update'),
                'effectsIndex' => route('organization.events.effects.index'),
            ],
            'strings' => [
                'title' => __('events.title'),
                'intro' => __('events.intro'),
                'effectsLink' => __('events.effects_link'),
                'colEvent' => __('events.col_event'),
                'targetStatus' => __('events.target_status'),
                'overridable' => __('events.overridable'),
                'noStatusChange' => __('events.no_status_change'),
                'overridableHint' => __('events.overridable_hint'),
                'save' => __('events.save'),
            ],
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

    public function effects(Request $request): InertiaResponse
    {
        $organization = $this->ownedOrganization($request);

        $statuses = $organization->statuses()->get();
        $configs = $this->configs($organization);

        // Gruppen inkl. je Event konfiguriertem Zielstatus (für die readonly
        // Mittelspalte "Automationen der Spalte").
        $groups = array_map(fn (string $group) => [
            'title' => __('events.group_'.$group),
            'events' => array_map(fn (TaskEvent $event) => [
                'value' => $event->value,
                'label' => $event->label(),
                'targetStatusId' => $configs->get($event->value)?->target_status_id,
            ], TaskEvent::forGroup($group)),
        ], TaskEvent::groups());

        // eventValue => { effects: [{field,value,only_if_empty}] } (editierbar).
        $eventConfigs = $configs->map(fn ($config) => [
            'effects' => array_map(fn ($fx) => [
                'field' => $fx['field'] ?? '',
                'value' => $fx['value'] ?? '',
                'only_if_empty' => ! empty($fx['only_if_empty']),
            ], $config->effects ?? []),
        ]);

        return Inertia::render('OrganizationEventsEffects', [
            'tabs' => OrganizationTabs::for('events'),
            'flash' => [
                'status' => $request->session()->get('status'),
                'error' => $request->session()->get('error'),
            ],
            'groups' => $groups,
            'configs' => (object) $eventConfigs->all(),
            'effectFields' => StatusEffects::ALLOWED_FIELDS,
            // status_id => label / on-enter effects, for the read-only "column automations".
            'statusLabels' => (object) $statuses->mapWithKeys(fn ($s) => [$s->id => $s->label])->all(),
            'statusEffects' => (object) $statuses->mapWithKeys(
                fn ($s) => [$s->id => array_map(fn ($fx) => [
                    'field' => $fx['field'] ?? '',
                    'value' => $fx['value'] ?? '',
                    'only_if_empty' => ! empty($fx['only_if_empty']),
                ], $s->on_enter_effects ?? [])]
            )->all(),
            'urls' => [
                'updateEffects' => route('organization.events.effects.update'),
                'eventsIndex' => route('organization.events.index'),
            ],
            'strings' => [
                'title' => __('events.effects_title'),
                'intro' => __('events.effects_intro'),
                'backToEvents' => __('events.back_to_events'),
                'colEvent' => __('events.col_event'),
                'columnAutomations' => __('events.column_automations'),
                'extraEffects' => __('events.extra_effects'),
                'effectField' => __('events.effect_field'),
                'effectValuePlaceholder' => __('events.effect_value_placeholder'),
                'effectOnlyIfEmpty' => __('events.effect_only_if_empty'),
                'addEffect' => __('events.add_effect'),
                'save' => __('events.save'),
            ],
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
