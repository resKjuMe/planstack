<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\OrgStatus;
use App\Models\OrgStatusAutomation;
use App\Models\OrgStatusGroup;
use App\Models\OrgStatusTransition;
use Illuminate\Support\Facades\DB;

/**
 * Seeds an organization with the default task-status configuration — a snapshot
 * of today's fixed workflow (App\Enums\TaskStatus + App\Support\BoardWorkflow),
 * minus the retired UNKNOWN ("ausstehend"): the initial status is PICKABLE.
 *
 * The default keys equal the legacy TaskStatus values, so a default-seeded org
 * stays wire-compatible with existing API/MCP clients. Idempotent: does nothing
 * if the org already has statuses.
 */
class DefaultTaskStatuses
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private const STATUSES = [
        // key/role, label(de), label_en, kind, color_token, position, is_column,
        // default_expanded, wip_limit, done, delivered, group
        ['PICKABLE',    'pickbar',      'pickable',    'waiting',   'indigo',  0, true,  true,  null, false, false, null],
        ['CLAIMED',     'beansprucht',  'claimed',     'active',    'sky',     1, true,  false, null, false, false, 'in_work'],
        ['ANALYZING',   'in Analyse',   'analyzing',   'active',    'blue',    2, true,  false, null, false, false, 'in_work'],
        ['IN_PROGRESS', 'in Arbeit',    'in progress', 'active',    'navy',    3, true,  true,  5,    false, false, 'in_work'],
        ['IN_REVIEW',   'in Review',    'in review',   'review',    'purple',  4, true,  true,  3,    false, false, null],
        ['MERGED',      'gemerged',     'merged',      'done',      'emerald', 5, true,  false, null, true,  true,  null],
        ['COMPLETED',   'erledigt',     'completed',   'done',      'green',   6, true,  false, null, true,  true,  null],
        // Exception states: not columns, collected in the left-hand lane
        // (default_expanded = true → the lane is open by default, as before).
        ['BLOCKED',     'blockiert',    'blocked',     'exception', 'rose',    7, false, true,  null, false, false, null],
        ['CONCERNED',   'problematisch','concerned',   'exception', 'red',     8, false, true,  null, false, false, null],
    ];

    /**
     * from key => [allowed target keys]. Self-transition is implicit.
     *
     * @var array<string, array<int, string>>
     */
    private const TRANSITIONS = [
        'PICKABLE' => ['CLAIMED'],
        'CLAIMED' => ['ANALYZING', 'IN_PROGRESS', 'PICKABLE'],
        'ANALYZING' => ['IN_PROGRESS', 'IN_REVIEW', 'CLAIMED'],
        'IN_PROGRESS' => ['IN_REVIEW', 'COMPLETED', 'ANALYZING'],
        'IN_REVIEW' => ['COMPLETED', 'IN_PROGRESS'],
        'COMPLETED' => ['MERGED', 'IN_REVIEW'],
        'MERGED' => ['COMPLETED'],
        'BLOCKED' => ['PICKABLE', 'CLAIMED'],
        'CONCERNED' => ['PICKABLE', 'CLAIMED'],
    ];

    /**
     * Wired action => [target key, field effects]. Mirrors the side effects that
     * are hard-coded in the controllers/MCP today.
     *
     * @var array<int, array{0: string, 1: string, 2: array<int, array<string, mixed>>}>
     */
    private const AUTOMATIONS = [
        ['claim',           'CLAIMED',   [['field' => 'claimed_by_id', 'value' => '@actor'], ['field' => 'claimed_at', 'value' => '@now']]],
        ['release',         'PICKABLE',  [['field' => 'claimed_by_id', 'value' => '@clear'], ['field' => 'claimed_at', 'value' => '@clear']]],
        ['analyze',         'ANALYZING', []],
        ['in_progress',     'IN_PROGRESS', []],
        ['in_review',       'IN_REVIEW', []],
        ['done_with_pr',    'IN_REVIEW', []],
        ['done_without_pr', 'IN_PROGRESS', []],
        ['merge',           'MERGED',    [['field' => 'merged_at', 'value' => '@now', 'only_if_empty' => true]]],
        ['split_parent',    'COMPLETED', []],
        ['concern',         'CONCERNED', []],
        ['resolve_claimed', 'CLAIMED',   []],
        ['resolve_unclaimed', 'PICKABLE', []],
    ];

    /**
     * @var array<int, array{key: string, label: string}>
     */
    private const GROUPS = [
        ['key' => 'in_work', 'label' => 'In Arbeit'],
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

            $statusIdByKey = [];
            foreach (self::STATUSES as $s) {
                [$key, $label, $labelEn, $kind, $color, $position, $isColumn, $expanded, $wip, $done, $delivered, $group] = $s;
                $row = OrgStatus::create([
                    'organization_id' => $organization->id,
                    'role' => $key, // default: key == role
                    'key' => $key,
                    'label' => $label,
                    'label_en' => $labelEn,
                    'kind' => $kind,
                    'color_token' => $color,
                    'position' => $position,
                    'is_column' => $isColumn,
                    'default_expanded' => $expanded,
                    'wip_limit' => $wip,
                    'counts_as_done' => $done,
                    'counts_as_delivered' => $delivered,
                    'group_id' => $group ? $groupIdByKey[$group] : null,
                ]);
                $statusIdByKey[$key] = $row->id;
            }

            foreach (self::TRANSITIONS as $from => $targets) {
                foreach ($targets as $to) {
                    OrgStatusTransition::create([
                        'from_status_id' => $statusIdByKey[$from],
                        'to_status_id' => $statusIdByKey[$to],
                    ]);
                }
            }

            foreach (self::AUTOMATIONS as [$action, $target, $effects]) {
                OrgStatusAutomation::create([
                    'organization_id' => $organization->id,
                    'action' => $action,
                    'target_status_id' => $statusIdByKey[$target],
                    'effects' => $effects,
                ]);
            }
        });
    }
}
