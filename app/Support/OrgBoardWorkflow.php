<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\OrgStatus;
use App\Models\OrgStatusTransition;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the board workflow definition from an organization's configurable
 * statuses (DB) — the dynamic successor to the static BoardWorkflow. Produces
 * the same payload shape the React board consumes (toArray) plus a color-token
 * map, and answers transition questions from the transitions table.
 *
 * For a default-seeded organization this yields exactly the previous hard-coded
 * workflow, so the board is unchanged.
 */
class OrgBoardWorkflow
{
    /** @var Collection<int, OrgStatus> ordered by position */
    private Collection $statuses;

    private function __construct(private readonly Organization $organization)
    {
        $this->statuses = $organization->statuses()->with('group')->get();
    }

    public static function forOrganization(Organization $organization): self
    {
        return new self($organization);
    }

    private function localizedLabel(OrgStatus $s): string
    {
        return Str::startsWith(app()->getLocale(), 'en') && $s->label_en
            ? $s->label_en
            : $s->label;
    }

    /**
     * @return array<string, array<int, string>> fromKey => [allowed target keys]
     */
    public function transitionsMap(): array
    {
        $byId = $this->statuses->keyBy('id');
        $rows = OrgStatusTransition::query()
            ->whereIn('from_status_id', $this->statuses->pluck('id'))
            ->get();

        $map = [];
        foreach ($rows as $t) {
            $from = $byId->get($t->from_status_id)?->key;
            $to = $byId->get($t->to_status_id)?->key;
            if ($from !== null && $to !== null) {
                $map[$from][] = $to;
            }
        }

        return $map;
    }

    public function canTransition(string $fromKey, string $toKey): bool
    {
        if ($fromKey === $toKey) {
            return true;
        }

        return in_array($toKey, $this->transitionsMap()[$fromKey] ?? [], true);
    }

    /**
     * The full workflow definition for the React board — same shape as the old
     * BoardWorkflow::toArray(), plus `colors` (key => palette token).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $columns = $this->statuses->where('is_column', true);
        $exceptions = $this->statuses->where('is_column', false);

        $groups = $this->organization->statusGroups()->get()
            ->map(function ($group) {
                $statuses = $this->statuses
                    ->where('group_id', $group->id)
                    ->sortBy('position')
                    ->pluck('key')
                    ->values()
                    ->all();

                return ['key' => $group->key, 'label' => $group->label, 'statuses' => $statuses];
            })
            // Only groups that actually hold columns are useful as collapse bars.
            ->filter(fn ($g) => count($g['statuses']) > 1)
            ->values()
            ->all();

        return [
            'columnOrder' => $columns->pluck('key')->values()->all(),
            'exceptionStatuses' => $exceptions->pluck('key')->values()->all(),
            'defaultExpanded' => $this->statuses->where('default_expanded', true)->pluck('key')->values()->all(),
            'exceptionsDefaultExpanded' => $exceptions->where('default_expanded', true)->isNotEmpty(),
            'transitions' => $this->transitionsMap(),
            'wipLimits' => $columns->whereNotNull('wip_limit')
                ->mapWithKeys(fn ($s) => [$s->key => (int) $s->wip_limit])->all(),
            'collapseGroups' => $groups,
            'mergedInitialLimit' => BoardWorkflow::MERGED_INITIAL_LIMIT,
            'mergedStaleDays' => BoardWorkflow::MERGED_STALE_DAYS,
            'labels' => $this->statuses->mapWithKeys(fn ($s) => [$s->key => $this->localizedLabel($s)])->all(),
            'colors' => $this->statuses->mapWithKeys(fn ($s) => [$s->key => $s->color_token])->all(),
            // key => inner SVG markup (or null). Resolved from the finite icon
            // palette so the React board renders it without its own icon copy.
            'icons' => $this->statuses->mapWithKeys(fn ($s) => [$s->key => StatusIcons::svg($s->icon)])->all(),
        ];
    }
}
