<?php

namespace App\Support;

use App\Models\OrgStatus;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Persönliche Statistik EINES Nutzers über ALLE Projekte, die er sehen darf —
 * die Ich-Perspektive zur (Owner-only) Projekt-Performance-Seite.
 *
 * Warum serverseitig und nicht wie Board/Summary/Performance clientseitig aus dem
 * geteilten Store: der Store hält immer genau EIN Projekt. Projektübergreifend
 * gibt es nichts zu teilen, also wird hier aggregiert — im Muster von
 * {@see ProjectOverviewPresenter}. Anders als dort dürfen die Task-Rows geladen
 * werden: die Menge ist auf die Tasks EINES Nutzers begrenzt, und Median-Werte
 * (Zykluszeit, Schätzabweichung) lassen sich in SQL nicht sinnvoll bilden.
 *
 * Genutzte Task-Statistiken: effort_story_points/tokens/man_days, affected_files
 * gegen die Ist-Werte der PRs (changed_files/additions/deletions/commits/
 * comments/review_comments), claimed_at/merged_at, last_review_recommendation,
 * pr_ci_failed, pr_unresolved_threads, criticality, concern sowie der
 * org-konfigurierte Status (counts_as_done + Styling).
 */
class UserStatisticsPresenter
{
    /** Wochen im Verlaufsdiagramm. */
    private const WEEKS = 12;

    /** Zuletzt gelieferte Tasks in der Liste. */
    private const RECENT = 8;

    public function __construct(
        private readonly TaskReworkCounts $rework,
        private readonly TaskStatusDurations $durations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(User $user): array
    {
        $projectIds = $this->visibleProjectIds($user);

        if ($projectIds === []) {
            return $this->emptyPayload();
        }

        /** @var Collection<int, Task> $own */
        $own = Task::query()
            ->whereIn('project_id', $projectIds)
            ->where('claimed_by_id', $user->id)
            ->with(['orgStatus', 'project:id,alias,name', 'pullRequests', 'concern:id,task_id'])
            ->get();

        $reviewed = Task::query()
            ->whereIn('project_id', $projectIds)
            ->where('reviewed_by', $user->id)
            ->get(['id', 'claimed_by_id', 'last_reviewed_at', 'effort_story_points']);

        $delivered = $own->filter(fn (Task $t) => (bool) $t->orgStatus?->counts_as_done);
        $open = $own->reject(fn (Task $t) => (bool) $t->orgStatus?->counts_as_done);

        // Verweildauern aus dem Änderungsprotokoll — anders als statusBuckets
        // (Momentaufnahme der offenen Tasks) ein Verlaufswert über ALLE Tasks der
        // Person, inklusive Rückläufer. EINE Abfrage speist das eigene Panel und
        // die Balken in den beiden Tabellen.
        $durations = $this->durations->aggregate(
            $own,
            $user->organization?->statuses()->get()->keyBy('id') ?? collect(),
        );

        return [
            'kpis' => $this->kpis($own, $delivered, $open, $reviewed),
            'quality' => $this->quality($own, $this->rework->forTaskIds($own->pluck('id'))),
            'volume' => $this->volume($own),
            'weekly' => $this->weekly($delivered, $reviewed),
            'statusBuckets' => $this->statusBuckets($open),
            'statusDurations' => $durations['statuses'],
            'projects' => $this->perProject($own, $durations['perProject']),
            'recent' => $this->recent($delivered, $durations['perTask']),
        ];
    }

    /**
     * Projekte, die der Nutzer sehen darf — dieselbe Regel wie in der
     * Projektübersicht (Org-Owner sieht alle, sonst eigene + Team-Projekte).
     *
     * @return array<int, int>
     */
    private function visibleProjectIds(User $user): array
    {
        return VisibleProjects::idsFor($user);
    }

    /**
     * @param  Collection<int, Task>  $own
     * @param  Collection<int, Task>  $delivered
     * @param  Collection<int, Task>  $open
     * @param  Collection<int, Task>  $reviewed
     * @return array<string, mixed>
     */
    private function kpis(Collection $own, Collection $delivered, Collection $open, Collection $reviewed): array
    {
        $cycles = $this->cycleDays($delivered);
        $deliveredSp = (int) $delivered->sum('effort_story_points');

        // Velocity: SP über die Spanne erster Claim → letzter Merge. Bezugsmenge sind
        // NUR die Tasks mit beidem (Claim und Merge) — zählte man die SP aller
        // gelieferten Tasks über die Spanne der datierten, käme eine zu hohe
        // Wochenleistung heraus. Erst ab zwei Lieferungen und einem Tag Spanne,
        // sonst ließe sich aus einem einzigen Task eine Fantasiezahl hochrechnen.
        // Verglichen wird über Unix-Timestamps, nicht über Carbon-Objekte — min()
        // und max() einer Collection vergleichen mit `<`.
        $timed = $delivered
            ->map(fn (Task $t) => [
                'sp' => (int) $t->effort_story_points,
                'claim' => $t->claimed_at?->getTimestamp(),
                'merge' => $this->mergedAt($t)?->getTimestamp(),
            ])
            ->filter(fn (array $r) => $r['claim'] !== null && $r['merge'] !== null)
            ->values();

        $spanDays = $timed->isNotEmpty()
            ? max(0.0, ($timed->max('merge') - $timed->min('claim')) / 86400)
            : null;
        $timedSp = (int) $timed->sum('sp');
        $spPerWeek = $spanDays !== null && $spanDays >= 1 && $timedSp > 0 && $timed->count() >= 2
            ? ($timedSp / $spanDays) * 7
            : null;

        // Letzte Lieferung: hier zählt JEDER datierte Merge, nicht nur die mit Claim.
        $lastMergeTs = $delivered
            ->map(fn (Task $t) => $this->mergedAt($t)?->getTimestamp())
            ->filter(fn (?int $ts) => $ts !== null)
            ->max();

        // Alter des ältesten noch offenen Claims — zeigt liegen gebliebene Arbeit.
        $oldestOpenClaimTs = $open
            ->map(fn (Task $t) => $t->claimed_at?->getTimestamp())
            ->filter(fn (?int $ts) => $ts !== null)
            ->min();

        $deviations = $this->deviations($delivered);
        // filter() OHNE Callback würde eine echte 0-Tage-Zykluszeit als „falsy"
        // wegwerfen — bei agentengetriebenen Boards ist die aber der Normalfall.
        $perSp = $delivered
            ->map(function (Task $t) {
                $days = $this->cycleFor($t);
                $sp = (int) $t->effort_story_points;

                return $days !== null && $sp > 0 ? $days / $sp : null;
            })
            ->filter(fn (?float $v) => $v !== null)
            ->values();

        return [
            'deliveredTasks' => $delivered->count(),
            'deliveredSp' => $deliveredSp,
            'openTasks' => $open->count(),
            'openSp' => (int) $open->sum('effort_story_points'),
            'totalTasks' => $own->count(),
            'projectCount' => $own->pluck('project_id')->unique()->count(),
            'cycleMedianDays' => $this->median($cycles),
            'cycleAvgDays' => $cycles->isNotEmpty() ? $cycles->avg() : null,
            'timePerSpDays' => $this->median($perSp),
            'spPerWeek' => $spPerWeek,
            'accuracyHits' => $deviations->filter(fn ($d) => abs($d) <= 25)->count(),
            'accuracyTotal' => $deviations->count(),
            'medianDeviationPct' => $this->median($deviations),
            'reviewsGiven' => $reviewed->count(),
            'reviewedAuthors' => $reviewed
                ->pluck('claimed_by_id')
                ->filter()
                ->unique()
                ->count(),
            'lastDeliveryAt' => $lastMergeTs !== null
                ? CarbonImmutable::createFromTimestamp($lastMergeTs)->toIso8601String()
                : null,
            'oldestOpenClaimDays' => $oldestOpenClaimTs !== null
                ? (time() - $oldestOpenClaimTs) / 86400
                : null,
        ];
    }

    /**
     * Qualitätskennzahlen. Die meisten davon sind MOMENTAUFNAHMEN des aktuellen
     * Zustands: `last_review_recommendation` hält nur das letzte Review, die
     * CI-/Thread-Zahlen den letzten gesyncten PR-Stand, und ein aufgelöster Concern
     * wird gelöscht. Nur `reworkTasks` ist ein Verlaufswert (Audit-Log).
     *
     * Wo die Datenbasis fehlt, wird `null` geliefert statt 0 — der Client zeigt
     * dann „—". Sonst sähe „nie gemessen" wie „alles in Ordnung" aus.
     *
     * @param  Collection<int, Task>  $own
     * @param  array<int, int>  $reworkCounts  Task-ID → protokollierte REQUEST_CHANGES
     * @return array<string, int|null>
     */
    private function quality(Collection $own, array $reworkCounts): array
    {
        $reviewed = $own->filter(fn (Task $t) => $t->last_review_recommendation !== null);

        // CI und Review-Threads sind nur aussagekräftig, wenn der PR-Status je
        // geholt wurde (planstack:sync-pr-status).
        $prSynced = $own->filter(fn (Task $t) => $t->pr_status_synced_at !== null)->count();

        // criticality ist ein optionales Pflegefeld.
        $criticalityKnown = $own->filter(fn (Task $t) => $t->criticality !== null)->count();

        return [
            'reviewedCount' => $reviewed->count(),
            'approved' => $reviewed->filter(fn (Task $t) => $t->last_review_recommendation?->value === 'APPROVE')->count(),
            'requestChanges' => $reviewed->filter(fn (Task $t) => $t->last_review_recommendation?->value === 'REQUEST_CHANGES')->count(),
            'reworkTasks' => $own->filter(fn (Task $t) => ($reworkCounts[$t->id] ?? 0) > 0)->count(),
            'reworkMultiple' => $own->filter(fn (Task $t) => ($reworkCounts[$t->id] ?? 0) > 1)->count(),
            'reworkTotal' => array_sum($reworkCounts),
            'tasksTotal' => $own->count(),
            'prSynced' => $prSynced,
            'ciFailed' => $prSynced > 0 ? $own->filter(fn (Task $t) => (int) $t->pr_ci_failed > 0)->count() : null,
            'openThreads' => $prSynced > 0 ? (int) $own->sum('pr_unresolved_threads') : null,
            'concerns' => $own->filter(fn (Task $t) => $t->concern !== null)->count(),
            'criticalityKnown' => $criticalityKnown,
            'critical' => $criticalityKnown > 0
                ? $own->filter(fn (Task $t) => in_array($t->criticality?->value, ['high', 'critical'], true))->count()
                : null,
        ];
    }

    /**
     * @param  Collection<int, Task>  $own
     * @return array<string, int>
     */
    private function volume(Collection $own): array
    {
        $prs = $own->flatMap(fn (Task $t) => $t->pullRequests);

        return [
            'prs' => $prs->count(),
            'files' => (int) $prs->sum('changed_files'),
            'additions' => (int) $prs->sum('additions'),
            'deletions' => (int) $prs->sum('deletions'),
            'commits' => (int) $prs->sum('commits'),
            'comments' => (int) $prs->sum('comments'),
            'reviewComments' => (int) $prs->sum('review_comments'),
            'tokens' => (int) $own->sum('effort_tokens'),
            'manDays' => (float) $own->sum('effort_man_days'),
        ];
    }

    /**
     * Wochenleistung, lückenlos über die letzten Wochen (Wochen ohne Aktivität
     * stehen als 0 im Diagramm — sonst täuschte die Kurve Kontinuität vor). Zwei
     * gestapelte Reihen, weil beides Arbeit ist:
     *
     *  - `sp`/`tasks`: selbst GELIEFERTE Tasks, einsortiert nach Merge-Zeitpunkt.
     *  - `reviewedSp`/`reviewedTasks`: Tasks, die die Person GEREVIEWT hat,
     *    einsortiert nach `last_reviewed_at`. Die Story Points sind die des
     *    Autors — sie stehen hier für den Umfang des Reviewten, nicht für eigene
     *    Lieferung, und werden deshalb getrennt ausgewiesen statt addiert.
     *
     * Grenze der Review-Reihe: `last_reviewed_at` hält nur das LETZTE Review, ein
     * mehrfach gereviewter Task erscheint also einmal.
     *
     * @param  Collection<int, Task>  $delivered
     * @param  Collection<int, Task>  $reviewed
     * @return array<int, array<string, mixed>>
     */
    private function weekly(Collection $delivered, Collection $reviewed): array
    {
        $buckets = [];
        $start = CarbonImmutable::now()->startOfWeek();

        for ($i = self::WEEKS - 1; $i >= 0; $i--) {
            $week = $start->subWeeks($i);
            $buckets[$week->format('o-W')] = [
                'key' => $week->format('o-W'),
                'label' => 'KW '.$week->isoWeek(),
                'sp' => 0,
                'tasks' => 0,
                'reviewedSp' => 0,
                'reviewedTasks' => 0,
            ];
        }

        foreach ($delivered as $task) {
            $mergedAt = $this->mergedAt($task);
            if ($mergedAt === null) {
                continue;
            }
            $key = $mergedAt->format('o-W');
            if (! isset($buckets[$key])) {
                continue; // älter als das Fenster
            }
            $buckets[$key]['sp'] += (int) $task->effort_story_points;
            $buckets[$key]['tasks']++;
        }

        foreach ($reviewed as $task) {
            if ($task->last_reviewed_at === null) {
                continue;
            }
            $key = CarbonImmutable::parse($task->last_reviewed_at)->format('o-W');
            if (! isset($buckets[$key])) {
                continue;
            }
            $buckets[$key]['reviewedSp'] += (int) $task->effort_story_points;
            $buckets[$key]['reviewedTasks']++;
        }

        return array_values($buckets);
    }

    /**
     * Offene Tasks nach ihrem org-konfigurierten Status, inkl. Styling — dieselben
     * Farben wie Board und Summary.
     *
     * @param  Collection<int, Task>  $open
     * @return array<int, array<string, mixed>>
     */
    private function statusBuckets(Collection $open): array
    {
        return $open
            ->groupBy(fn (Task $t) => $t->orgStatus?->key ?? '—')
            ->map(function (Collection $group, string $key) {
                /** @var OrgStatus|null $status */
                $status = $group->first()->orgStatus;

                return [
                    'key' => $key,
                    'label' => $status !== null ? $this->statusLabel($status) : $key,
                    'count' => $group->count(),
                    'sp' => (int) $group->sum('effort_story_points'),
                    'position' => (int) ($status?->position ?? 0),
                    'bar' => StatusPalette::bar($status?->color_token),
                    'badge' => StatusPalette::badge($status?->color_token),
                ];
            })
            ->sortByDesc('position')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Task>  $own
     * @param  array<int|string, array<string, mixed>>  $durationsByProject  Verweildauer-Segmente je Projekt
     * @return array<int, array<string, mixed>>
     */
    private function perProject(Collection $own, array $durationsByProject): array
    {
        return $own
            ->groupBy('project_id')
            ->map(function (Collection $group, $projectId) use ($durationsByProject) {
                $project = $group->first()->project;
                $delivered = $group->filter(fn (Task $t) => (bool) $t->orgStatus?->counts_as_done);
                $prs = $group->flatMap(fn (Task $t) => $t->pullRequests);
                $cycles = $this->cycleDays($delivered);

                return [
                    'alias' => $project?->alias,
                    'name' => $project?->name,
                    'url' => $project !== null ? route('projects.show', $project) : null,
                    'deliveredTasks' => $delivered->count(),
                    'deliveredSp' => (int) $delivered->sum('effort_story_points'),
                    'openTasks' => $group->count() - $delivered->count(),
                    'openSp' => (int) $group->reject(fn (Task $t) => (bool) $t->orgStatus?->counts_as_done)->sum('effort_story_points'),
                    'totalTasks' => $group->count(),
                    'cycleMedianDays' => $this->median($cycles),
                    'files' => (int) $prs->sum('changed_files'),
                    'commits' => (int) $prs->sum('commits'),
                    // Balken „wo lag die Zeit": Segmente je Bearbeitungs-Status.
                    'durations' => $durationsByProject[$projectId] ?? null,
                ];
            })
            ->sortByDesc('deliveredSp')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Task>  $delivered
     * @param  array<int|string, array<string, mixed>>  $durationsByTask  Verweildauer-Segmente je Task
     * @return array<int, array<string, mixed>>
     */
    private function recent(Collection $delivered, array $durationsByTask): array
    {
        return $delivered
            ->filter(fn (Task $t) => $this->mergedAt($t) !== null)
            ->sortByDesc(fn (Task $t) => $this->mergedAt($t)?->getTimestamp() ?? 0)
            ->take(self::RECENT)
            ->map(function (Task $t) use ($durationsByTask) {
                $estimated = $t->affected_files !== null ? (int) $t->affected_files : null;
                $actual = $t->pullRequests->isNotEmpty() ? (int) $t->pullRequests->sum('changed_files') : null;

                return [
                    'name' => $t->name,
                    'summary' => $t->summary,
                    'projectAlias' => $t->project?->alias,
                    'url' => $t->project !== null ? route('projects.tasks.show', [$t->project, $t->id]) : null,
                    'sp' => $t->effort_story_points,
                    'mergedAt' => $this->mergedAt($t)?->toIso8601String(),
                    'cycleDays' => $this->cycleFor($t),
                    'filesEstimated' => $estimated,
                    'filesActual' => $actual,
                    'deviationPct' => $estimated !== null && $estimated > 0 && $actual !== null
                        ? (int) round((($actual - $estimated) / $estimated) * 100)
                        : null,
                    // Balken „wo lag die Zeit": Segmente je Bearbeitungs-Status.
                    'durations' => $durationsByTask[$t->id] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    // --- kleine Helfer ---------------------------------------------------------

    /**
     * Merge-Zeitpunkt: der des Tasks, sonst der spätere der zugehörigen PRs.
     * Beides wird gepflegt, aber nicht immer beides.
     */
    private function mergedAt(Task $task): ?CarbonImmutable
    {
        $at = $task->merged_at ?? $task->pullRequests->pluck('merged_at')->filter()->max();

        return $at !== null ? CarbonImmutable::parse($at) : null;
    }

    /** Zykluszeit eines Tasks in Tagen (Claim → Merge), oder null. */
    private function cycleFor(Task $task): ?float
    {
        $mergedAt = $this->mergedAt($task);

        if ($task->claimed_at === null || $mergedAt === null) {
            return null;
        }

        $days = CarbonImmutable::parse($task->claimed_at)->diffInRealSeconds($mergedAt) / 86400;

        return $days >= 0 ? $days : null;
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, float>
     */
    private function cycleDays(Collection $tasks): Collection
    {
        return $tasks->map(fn (Task $t) => $this->cycleFor($t))->filter(fn ($v) => $v !== null)->values();
    }

    /**
     * Abweichung der Ist-Dateien von der Schätzung (`affected_files`) in Prozent,
     * je Task mit beidem.
     *
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, int>
     */
    private function deviations(Collection $tasks): Collection
    {
        return $tasks
            ->map(function (Task $t) {
                $estimated = (int) $t->affected_files;
                if ($estimated <= 0 || $t->pullRequests->isEmpty()) {
                    return null;
                }
                $actual = (int) $t->pullRequests->sum('changed_files');

                return (int) round((($actual - $estimated) / $estimated) * 100);
            })
            ->filter(fn ($v) => $v !== null)
            ->values();
    }

    /**
     * @param  Collection<int, float|int>  $values
     */
    private function median(Collection $values): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        $sorted = $values->sort()->values();
        $count = $sorted->count();
        $mid = intdiv($count, 2);

        return $count % 2 === 1
            ? (float) $sorted[$mid]
            : ((float) $sorted[$mid - 1] + (float) $sorted[$mid]) / 2;
    }

    private function statusLabel(OrgStatus $status): string
    {
        return Str::startsWith(app()->getLocale(), 'en') && $status->label_en
            ? $status->label_en
            : $status->label;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(): array
    {
        return [
            'kpis' => [
                'deliveredTasks' => 0, 'deliveredSp' => 0, 'openTasks' => 0, 'openSp' => 0,
                'totalTasks' => 0, 'projectCount' => 0,
                'cycleMedianDays' => null, 'cycleAvgDays' => null, 'timePerSpDays' => null, 'spPerWeek' => null,
                'accuracyHits' => 0, 'accuracyTotal' => 0, 'medianDeviationPct' => null,
                'reviewsGiven' => 0, 'reviewedAuthors' => 0,
                'lastDeliveryAt' => null, 'oldestOpenClaimDays' => null,
            ],
            'quality' => [
                'reviewedCount' => 0, 'approved' => 0, 'requestChanges' => 0,
                'reworkTasks' => 0, 'reworkMultiple' => 0, 'reworkTotal' => 0, 'tasksTotal' => 0,
                'prSynced' => 0, 'ciFailed' => null, 'openThreads' => null,
                'concerns' => 0, 'criticalityKnown' => 0, 'critical' => null,
            ],
            'volume' => [
                'prs' => 0, 'files' => 0, 'additions' => 0, 'deletions' => 0,
                'commits' => 0, 'comments' => 0, 'reviewComments' => 0, 'tokens' => 0, 'manDays' => 0.0,
            ],
            'weekly' => [],
            'statusBuckets' => [],
            'statusDurations' => [],
            'projects' => [],
            'recent' => [],
        ];
    }
}
