<?php

namespace App\Support;

use App\Models\OrgStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the stacked status progress-bar segments (summary phase bars + project
 * overview) from an organization's CONFIGURABLE statuses — so custom statuses
 * appear with their own label and color instead of being collapsed onto a
 * canonical enum value. Tasks are grouped by the same board display key the
 * React board uses (via BoardPresenter), so waiting tasks still resolve to
 * PICKABLE/BLOCKED by their gate.
 */
class StatusSegments
{
    public function __construct(private readonly BoardPresenter $presenter) {}

    /** @var array<int, Collection<int, OrgStatus>> memoized ordered statuses per project */
    private array $orderedCache = [];

    /**
     * Ordered status segments present in $tasks. Each: label, count, bar, text,
     * badge, width (SP share, %).
     *
     * @param  Collection<int, Task>  $tasks  decorated tasks (x_* attributes set)
     * @return array<int, array<string, mixed>>
     */
    public function segments(Project $project, Collection $tasks): array
    {
        $keys = $this->presenter->displayKeysFor($tasks, $project);

        $count = [];
        $sp = [];
        foreach ($tasks as $task) {
            $key = $keys[$task->id];
            $count[$key] = ($count[$key] ?? 0) + 1;
            $sp[$key] = ($sp[$key] ?? 0) + (int) $task->effort_story_points;
        }

        // Segments are weighted by story points; if the set carries no story
        // points at all, fall back to the task count so the bar still shows a
        // meaningful breakdown instead of staying empty.
        $totalSp = array_sum($sp);
        $useSp = $totalSp > 0;
        $totalWeight = max(1, $useSp ? $totalSp : array_sum($count));

        $segments = [];
        foreach ($this->ordered($project) as $status) {
            if (($count[$status->key] ?? 0) === 0) {
                continue;
            }
            $weight = $useSp ? $sp[$status->key] : $count[$status->key];
            $segments[] = [
                'label' => $this->label($status),
                'count' => $count[$status->key],
                'bar' => StatusPalette::bar($status->color_token),
                'text' => StatusPalette::text($status->color_token),
                'badge' => StatusPalette::badge($status->color_token),
                'width' => round($weight / $totalWeight * 100, 1),
            ];
        }

        return $segments;
    }

    /**
     * Client-Konfiguration für die Summary-Ableitung im Browser: die Org-Statuses
     * in derselben Balken-Reihenfolge wie {@see segments()} (fertig → … → Ausnahme),
     * je Status inkl. Styling-Klassen (bar/text/badge) und der done/delivered-
     * Flags, plus die role→key-Map. Damit kann der React-Store die Phasen-Balken,
     * Fortschritts-KPIs und Blocker rein clientseitig aus den Tasks berechnen.
     *
     * @return array{statuses: array<int, array<string, mixed>>, roleKey: array<string, string>}
     */
    public function config(Project $project): array
    {
        return $this->configForStatuses($project->organization->statuses()->get());
    }

    /**
     * Org-weite Variante (unabhängig vom einzelnen Projekt) — genutzt vom
     * org-weiten Endpunkt GET /api/status-config, den der Client EINMAL lädt und
     * über alle Projekte/Unterseiten wiederverwendet.
     *
     * @return array{statuses: array<int, array<string, mixed>>, roleKey: array<string, string>}
     */
    public function configForOrganization(\App\Models\Organization $organization): array
    {
        return $this->configForStatuses($organization->statuses()->get());
    }

    /**
     * @param  Collection<int, OrgStatus>  $statuses
     * @return array{statuses: array<int, array<string, mixed>>, roleKey: array<string, string>}
     */
    private function configForStatuses(Collection $statuses): array
    {
        $ordered = $statuses->sort(function (OrgStatus $a, OrgStatus $b) {
            $rank = self::kindRank($a->kind) <=> self::kindRank($b->kind);

            return $rank !== 0 ? $rank : $b->position <=> $a->position;
        })->values();

        $mapped = $ordered->map(fn (OrgStatus $s) => [
            'key' => $s->key,
            'label' => $this->label($s),
            'kind' => $s->kind,
            'role' => $s->role?->value,
            'color_token' => $s->color_token,
            'position' => $s->position,
            'counts_as_done' => (bool) $s->counts_as_done,
            'counts_as_delivered' => (bool) $s->counts_as_delivered,
            'bar' => StatusPalette::bar($s->color_token),
            'text' => StatusPalette::text($s->color_token),
            'badge' => StatusPalette::badge($s->color_token),
            // Inneres SVG-Markup des Status-Icons (für das Diagramm) — kann null sein.
            'icon' => StatusIcons::svg($s->icon),
        ])->values()->all();

        $roleKey = $ordered->whereNotNull('role')
            ->mapWithKeys(fn (OrgStatus $s) => [$s->role->value => $s->key])
            ->all();

        return ['statuses' => $mapped, 'roleKey' => $roleKey];
    }

    /**
     * Attach per-task display presentation (x_display_key, x_status_label,
     * x_status_badge) so lists can show the configured status — including custom
     * ones — instead of the enum fallback.
     *
     * @param  Collection<int, Task>  $tasks
     */
    public function annotate(Project $project, Collection $tasks): void
    {
        $keys = $this->presenter->displayKeysFor($tasks, $project);
        $byKey = $this->ordered($project)->keyBy('key');

        foreach ($tasks as $task) {
            $key = $keys[$task->id];
            $status = $byKey->get($key);
            $task->x_display_key = $key;
            $task->x_status_label = $status ? $this->label($status) : $key;
            $task->x_status_badge = StatusPalette::badge($status?->color_token);
            // Role (canonical action role or null for custom), kind and color
            // token — let role/kind-aware views (PR sequence) present custom
            // statuses without falling back to the enum.
            $task->x_status_role = $status?->role?->value;
            $task->x_status_kind = $status?->kind;
            $task->x_status_color = $status?->color_token;
            $task->x_status_icon = StatusIcons::svg($status?->icon);
        }
    }

    /**
     * Statuses ordered for the stacked bar: most-complete first (done → review →
     * active → waiting → exception), within a kind by board position descending.
     *
     * @return Collection<int, OrgStatus>
     */
    private function ordered(Project $project): Collection
    {
        return $this->orderedCache[$project->id] ??= $project->organization->statuses()->get()
            ->sort(function (OrgStatus $a, OrgStatus $b) {
                $rank = self::kindRank($a->kind) <=> self::kindRank($b->kind);

                return $rank !== 0 ? $rank : $b->position <=> $a->position;
            })
            ->values();
    }

    private static function kindRank(?string $kind): int
    {
        return match ($kind) {
            'done' => 0,
            'review' => 1,
            'active' => 2,
            'waiting' => 3,
            'exception' => 4,
            default => 5,
        };
    }

    private function label(OrgStatus $status): string
    {
        return Str::startsWith(app()->getLocale(), 'en') && $status->label_en
            ? $status->label_en
            : $status->label;
    }
}
