<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\OrgEventAutomation;
use App\Models\OrgStatus;
use App\Models\OrgStatusAutomation;
use App\Models\OrgStatusGroup;
use App\Models\OrgStatusTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds a newly created organization with the default task-workflow
 * configuration. This is a snapshot of the reference organization's live setup
 * (statuses, groups, transitions, wired-action automations and event
 * automations) — the "standard" a fresh organization starts from.
 *
 * Applied on organization creation (see App\Observers\OrganizationObserver).
 * Idempotent: does nothing if the org already has statuses. Guarded so it also
 * works when invoked from an early migration before later columns/tables exist
 * (those are backfilled by their own migrations); at runtime everything exists.
 */
class DefaultTaskStatuses
{
    /**
     * Statuses in board order. `role` is a canonical StatusRole value or null for
     * a custom (org-defined) status. `group` references a GROUPS key.
     *
     * @var array<int, array<string, mixed>>
     */
    private const STATUSES = [
        ['key' => 'PICKABLE',    'role' => 'PICKABLE',    'label' => 'pickbar',       'label_en' => 'pickable',   'kind' => 'waiting',   'color' => 'slate',   'icon' => 'inbox',          'position' => 0,  'is_column' => true,  'expanded' => true,  'wip' => null, 'done' => false, 'delivered' => false, 'group' => null],
        ['key' => 'CLAIMED',     'role' => 'CLAIMED',     'label' => 'beansprucht',   'label_en' => 'claimed',    'kind' => 'active',    'color' => 'sky',     'icon' => 'hand',           'position' => 1,  'is_column' => true,  'expanded' => false, 'wip' => null, 'done' => false, 'delivered' => false, 'group' => 'in_work'],
        ['key' => 'ANALYZING',   'role' => 'ANALYZING',   'label' => 'in Analyse',    'label_en' => 'analyzing',  'kind' => 'active',    'color' => 'blue',    'icon' => 'search',         'position' => 2,  'is_column' => true,  'expanded' => false, 'wip' => null, 'done' => false, 'delivered' => false, 'group' => 'in_work'],
        ['key' => 'IN_PROGRESS', 'role' => 'IN_PROGRESS', 'label' => 'in Arbeit',     'label_en' => 'in progress','kind' => 'active',    'color' => 'navy',    'icon' => 'hammer',         'position' => 3,  'is_column' => true,  'expanded' => true,  'wip' => null, 'done' => false, 'delivered' => false, 'group' => 'in_work'],
        ['key' => 'REVIEWBAR',   'role' => null,          'label' => 'reviewbar',     'label_en' => 'reviewable', 'kind' => 'review',    'color' => 'indigo',  'icon' => 'user-check',     'position' => 4,  'is_column' => true,  'expanded' => false, 'wip' => null, 'done' => false, 'delivered' => false, 'group' => 'in-review'],
        ['key' => 'IN_REVIEW',   'role' => 'IN_REVIEW',   'label' => 'in Review',     'label_en' => 'in review',  'kind' => 'review',    'color' => 'purple',  'icon' => 'eye',            'position' => 5,  'is_column' => true,  'expanded' => true,  'wip' => null, 'done' => false, 'delivered' => false, 'group' => 'in-review'],
        ['key' => 'APPROVED',    'role' => null,          'label' => 'approved',      'label_en' => 'approved',   'kind' => 'review',    'color' => 'purple',  'icon' => 'check',          'position' => 6,  'is_column' => true,  'expanded' => false, 'wip' => null, 'done' => false, 'delivered' => false, 'group' => 'in-review'],
        ['key' => 'MERGED',      'role' => 'MERGED',      'label' => 'gemerged',      'label_en' => 'merged',     'kind' => 'done',      'color' => 'green',   'icon' => 'git-merge',      'position' => 7,  'is_column' => true,  'expanded' => false, 'wip' => null, 'done' => true,  'delivered' => true,  'group' => 'done'],
        ['key' => 'COMPLETED',   'role' => 'COMPLETED',   'label' => 'erledigt',      'label_en' => 'completed',  'kind' => 'done',      'color' => 'green',   'icon' => 'circle-check',   'position' => 8,  'is_column' => true,  'expanded' => false, 'wip' => null, 'done' => true,  'delivered' => true,  'group' => 'done'],
        ['key' => 'BLOCKED',     'role' => 'BLOCKED',     'label' => 'blockiert',     'label_en' => 'blocked',    'kind' => 'exception', 'color' => 'rose',    'icon' => 'octagon-x',      'position' => 9,  'is_column' => false, 'expanded' => false, 'wip' => null, 'done' => false, 'delivered' => false, 'group' => null],
        ['key' => 'CONCERNED',   'role' => 'CONCERNED',   'label' => 'problematisch', 'label_en' => 'concerned',  'kind' => 'exception', 'color' => 'orange',  'icon' => 'triangle-alert', 'position' => 10, 'is_column' => false, 'expanded' => false, 'wip' => null, 'done' => false, 'delivered' => false, 'group' => null],
    ];

    /**
     * Collapse groups, in order.
     *
     * @var array<int, array{key: string, label: string}>
     */
    private const GROUPS = [
        ['key' => 'in_work', 'label' => 'In Arbeit'],
        ['key' => 'in-review', 'label' => 'In Review'],
        ['key' => 'done', 'label' => 'Done'],
    ];

    /**
     * from key => [allowed target keys]. Self-transition is implicit.
     *
     * @var array<string, array<int, string>>
     */
    public const TRANSITIONS = [
        'PICKABLE' => ['CLAIMED', 'BLOCKED', 'CONCERNED'],
        'CLAIMED' => ['PICKABLE', 'ANALYZING', 'IN_PROGRESS', 'REVIEWBAR', 'BLOCKED', 'CONCERNED'],
        'ANALYZING' => ['CLAIMED', 'IN_PROGRESS', 'REVIEWBAR', 'BLOCKED', 'CONCERNED'],
        'IN_PROGRESS' => ['ANALYZING', 'REVIEWBAR', 'COMPLETED', 'BLOCKED', 'CONCERNED'],
        'REVIEWBAR' => ['IN_PROGRESS', 'IN_REVIEW', 'BLOCKED', 'CONCERNED'],
        'IN_REVIEW' => ['IN_PROGRESS', 'REVIEWBAR', 'APPROVED', 'MERGED', 'COMPLETED', 'BLOCKED', 'CONCERNED'],
        'APPROVED' => ['IN_PROGRESS', 'REVIEWBAR', 'IN_REVIEW', 'MERGED', 'COMPLETED', 'BLOCKED', 'CONCERNED'],
        'MERGED' => ['COMPLETED', 'BLOCKED', 'CONCERNED'],
        'COMPLETED' => ['IN_REVIEW', 'MERGED', 'BLOCKED', 'CONCERNED'],
        'BLOCKED' => ['PICKABLE', 'CLAIMED', 'ANALYZING', 'IN_PROGRESS', 'REVIEWBAR', 'IN_REVIEW', 'APPROVED', 'MERGED', 'COMPLETED', 'CONCERNED'],
        'CONCERNED' => ['PICKABLE', 'CLAIMED', 'ANALYZING', 'IN_PROGRESS', 'REVIEWBAR', 'IN_REVIEW', 'APPROVED', 'MERGED', 'COMPLETED', 'BLOCKED'],
    ];

    /**
     * Default on-enter field effects, keyed by status ROLE: applied when a task
     * enters that status. value tokens: @actor, @now, @clear. Applied via
     * applyDefaultEffects() (not in create(), since the on_enter_effects column
     * is added in a later migration than the historical seed).
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private const ON_ENTER = [
        'CLAIMED' => [
            ['field' => 'claimed_by_id', 'value' => '@actor', 'only_if_empty' => true],
            ['field' => 'claimed_at', 'value' => '@now', 'only_if_empty' => true],
        ],
        'PICKABLE' => [
            ['field' => 'claimed_by_id', 'value' => '@clear'],
            ['field' => 'claimed_at', 'value' => '@clear'],
        ],
        'IN_REVIEW' => [
            ['field' => 'reviewed_by', 'value' => '@actor', 'only_if_empty' => true],
        ],
        'MERGED' => [
            ['field' => 'merged_at', 'value' => '@now', 'only_if_empty' => true],
        ],
    ];

    /**
     * Wired-action automations (table task_status_automations): target status +
     * field effects per fixed action keyword. `target` references a status key.
     *
     * @var array<int, array{action: string, target: string, effects: array<int, array<string, mixed>>}>
     */
    private const STATUS_AUTOMATIONS = [
        ['action' => 'claim', 'target' => 'CLAIMED', 'effects' => [
            ['field' => 'claimed_by_id', 'value' => '@actor'],
            ['field' => 'claimed_at', 'value' => '@now'],
        ]],
        ['action' => 'release', 'target' => 'PICKABLE', 'effects' => [
            ['field' => 'claimed_by_id', 'value' => '@clear'],
            ['field' => 'claimed_at', 'value' => '@clear'],
        ]],
        ['action' => 'analyze', 'target' => 'ANALYZING', 'effects' => []],
        ['action' => 'in_progress', 'target' => 'IN_PROGRESS', 'effects' => []],
        ['action' => 'in_review', 'target' => 'IN_REVIEW', 'effects' => []],
        ['action' => 'done_with_pr', 'target' => 'IN_REVIEW', 'effects' => []],
        ['action' => 'done_without_pr', 'target' => 'IN_PROGRESS', 'effects' => []],
        ['action' => 'merge', 'target' => 'MERGED', 'effects' => [
            ['field' => 'merged_at', 'value' => '@now', 'only_if_empty' => true],
        ]],
        ['action' => 'split_parent', 'target' => 'COMPLETED', 'effects' => []],
        ['action' => 'concern', 'target' => 'CONCERNED', 'effects' => []],
        ['action' => 'resolve_claimed', 'target' => 'CLAIMED', 'effects' => []],
        ['action' => 'resolve_unclaimed', 'target' => 'PICKABLE', 'effects' => []],
    ];

    /**
     * Event automations (table task_event_automations): per progress event a
     * target status and the set of overwritable statuses. `target` and each
     * `overridable` entry reference a status key; overridable '*' = all statuses.
     * Only events with a target status are seeded (a null target is a no-op).
     *
     * @var array<int, array{event: string, target: string, overridable: string|array<int, string>}>
     */
    private const EVENT_AUTOMATIONS = [
        ['event' => 'CLAIMED', 'target' => 'CLAIMED', 'overridable' => ['PICKABLE']],
        ['event' => 'ANALYZING', 'target' => 'ANALYZING', 'overridable' => ['CLAIMED']],
        ['event' => 'PROCESSING', 'target' => 'IN_PROGRESS', 'overridable' => ['ANALYZING']],
        ['event' => 'POLISHED', 'target' => 'REVIEWBAR', 'overridable' => ['IN_PROGRESS']],
        ['event' => 'REVIEWING', 'target' => 'IN_REVIEW', 'overridable' => ['REVIEWBAR']],
        ['event' => 'APPROVED', 'target' => 'APPROVED', 'overridable' => ['IN_REVIEW']],
        ['event' => 'CHANGES_REQUESTED', 'target' => 'IN_PROGRESS', 'overridable' => ['IN_REVIEW']],
        ['event' => 'MERGED', 'target' => 'MERGED', 'overridable' => ['APPROVED']],
        ['event' => 'DEPLOYED', 'target' => 'COMPLETED', 'overridable' => ['MERGED']],
        ['event' => 'CONCERNED', 'target' => 'CONCERNED', 'overridable' => '*'],
        ['event' => 'UNCLAIMED', 'target' => 'PICKABLE', 'overridable' => '*'],
    ];

    public static function seed(Organization $organization): void
    {
        if ($organization->statuses()->exists()) {
            return; // already configured
        }

        DB::transaction(function () use ($organization) {
            $groupIdByKey = [];
            foreach (self::GROUPS as $i => $g) {
                $group = OrgStatusGroup::create([
                    'organization_id' => $organization->id,
                    'key' => $g['key'],
                    'label' => $g['label'],
                    'position' => $i,
                ]);
                $groupIdByKey[$g['key']] = $group->id;
            }

            // The icon column is added in a later migration than the historical
            // seed; only write it when it already exists (at runtime it does).
            $hasIcon = Schema::hasColumn('task_statuses', 'icon');

            $statusIdByKey = [];
            foreach (self::STATUSES as $s) {
                $attrs = [
                    'organization_id' => $organization->id,
                    'role' => $s['role'],
                    'key' => $s['key'],
                    'label' => $s['label'],
                    'label_en' => $s['label_en'],
                    'kind' => $s['kind'],
                    'color_token' => $s['color'],
                    'position' => $s['position'],
                    'is_column' => $s['is_column'],
                    'default_expanded' => $s['expanded'],
                    'wip_limit' => $s['wip'] ?? null,
                    'counts_as_done' => $s['done'] ?? false,
                    'counts_as_delivered' => $s['delivered'] ?? false,
                    'group_id' => $s['group'] ? ($groupIdByKey[$s['group']] ?? null) : null,
                ];
                if ($hasIcon) {
                    $attrs['icon'] = $s['icon'];
                }
                $statusIdByKey[$s['key']] = OrgStatus::create($attrs)->id;
            }

            foreach (self::TRANSITIONS as $from => $targets) {
                foreach ($targets as $to) {
                    if (isset($statusIdByKey[$from], $statusIdByKey[$to])) {
                        OrgStatusTransition::create([
                            'from_status_id' => $statusIdByKey[$from],
                            'to_status_id' => $statusIdByKey[$to],
                        ]);
                    }
                }
            }

            self::seedStatusAutomations($organization, $statusIdByKey);
            self::seedEventAutomations($organization, $statusIdByKey);
        });

        // Set default on-enter effects. Guarded so the seed still works when run
        // from an early migration before the on_enter_effects column exists (the
        // backfill migration applies them there instead).
        self::applyDefaultEffects($organization);
    }

    /**
     * Seed the wired-action automations. No-op if the table doesn't exist yet
     * (early-migration path).
     *
     * @param  array<string, int>  $statusIdByKey
     */
    private static function seedStatusAutomations(Organization $organization, array $statusIdByKey): void
    {
        if (! Schema::hasTable('task_status_automations')) {
            return;
        }

        foreach (self::STATUS_AUTOMATIONS as $a) {
            OrgStatusAutomation::create([
                'organization_id' => $organization->id,
                'action' => $a['action'],
                'target_status_id' => $statusIdByKey[$a['target']] ?? null,
                'effects' => $a['effects'] ?: null,
            ]);
        }
    }

    /**
     * Seed the per-event automations. No-op if the table doesn't exist yet
     * (the table is created in a migration later than the historical seed).
     *
     * @param  array<string, int>  $statusIdByKey
     */
    private static function seedEventAutomations(Organization $organization, array $statusIdByKey): void
    {
        if (! Schema::hasTable('task_event_automations')) {
            return;
        }

        $allIds = array_values($statusIdByKey);

        foreach (self::EVENT_AUTOMATIONS as $e) {
            $target = $statusIdByKey[$e['target']] ?? null;
            if ($target === null) {
                continue;
            }

            $overridable = $e['overridable'] === '*'
                ? $allIds
                : array_values(array_filter(array_map(
                    fn ($key) => $statusIdByKey[$key] ?? null,
                    (array) $e['overridable'],
                )));

            OrgEventAutomation::create([
                'organization_id' => $organization->id,
                'event' => $e['event'],
                'target_status_id' => $target,
                'overridable_status_ids' => $overridable ?: null,
                'effects' => null,
            ]);
        }
    }

    /**
     * Ensure every default transition edge exists for the org (add missing ones
     * by status key; never removes custom edges). Used to backfill the expanded
     * lifecycle graph onto existing organizations so the transition check on the
     * API/MCP actions matches the documented workflow.
     */
    public static function syncDefaultTransitions(Organization $organization): void
    {
        $idByKey = $organization->statuses()->pluck('id', 'key');

        foreach (self::TRANSITIONS as $from => $targets) {
            $fromId = $idByKey[$from] ?? null;
            if ($fromId === null) {
                continue;
            }
            foreach ($targets as $to) {
                $toId = $idByKey[$to] ?? null;
                if ($toId === null) {
                    continue;
                }
                OrgStatusTransition::firstOrCreate(['from_status_id' => $fromId, 'to_status_id' => $toId]);
            }
        }
    }

    /**
     * Set the default on-enter effects on an org's role-bearing statuses that
     * don't already have effects configured (never clobbers user customizations).
     * No-op if the on_enter_effects column does not exist yet.
     */
    public static function applyDefaultEffects(Organization $organization): void
    {
        if (! Schema::hasColumn('task_statuses', 'on_enter_effects')) {
            return;
        }

        foreach ($organization->statuses()->get() as $status) {
            $role = $status->role?->value;
            if ($role !== null && isset(self::ON_ENTER[$role]) && $status->on_enter_effects === null) {
                $status->update(['on_enter_effects' => self::ON_ENTER[$role]]);
            }
        }
    }
}
