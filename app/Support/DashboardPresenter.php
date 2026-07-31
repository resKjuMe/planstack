<?php

namespace App\Support;

use App\Models\OrgStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskEventLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Startseite eines Nutzers (Logo-Ziel): was liegt PROJEKTÜBERGREIFEND bei mir an.
 *
 * Kern ist die Ich-Sicht des Board-Filters „Bei mir" (resources/js/board/
 * components/Board.jsx → isMyWork), nur über alle sichtbaren Projekte statt über
 * eines:
 *
 *  - `work`     — Tasks in einem Arbeitsschritt (kind `active`), die ICH beansprucht habe;
 *  - `review`   — Tasks im Review (kind `review`), die mir gehören oder noch frei sind,
 *                 aber NUR fremde Arbeit (siehe unten);
 *  - `awaiting` — meine eigene Arbeit im Review — ob sie noch auf einen Reviewer
 *                 wartet oder schon bei einem liegt, steht an der Zeile;
 *  - `blocked`  — darüber hinaus: eigene Tasks in einer Ausnahme (kind `exception`,
 *                 also blockiert/Concern). Der Board-Chip lässt die aus, weil dort
 *                 die Ausnahme-Spalte daneben steht — auf dem Dashboard wäre die
 *                 Arbeit sonst unsichtbar.
 *
 * Eigenreview ist nicht erlaubt: die API weist ein Review am eigenen Task hart ab
 * (POST .../review-claim und .../review antworten 409, review-next filtert eigene
 * Claims heraus). Ein freies Review der eigenen Arbeit ist deshalb KEIN Auftrag an
 * mich und gehört nicht in die Gruppe `review` — es steht in `awaiting`, damit die
 * eigene Lieferung sichtbar bleibt, ohne als holbares Review zu zählen. „Eigene
 * Arbeit" heißt hier Ersteller ODER Beanspruchender: die API kennt nur den Claim,
 * aber auch ein Task, den ich geschrieben und jemand anderes umgesetzt hat, ist
 * kein neutrales Review.
 *
 * `awaiting` zählt dabei UNABHÄNGIG davon, wer reviewt: auch die eigene Lieferung,
 * die schon bei einem fremden Reviewer liegt, bleibt hier stehen — sie ist nicht
 * fertig, und ihr Stand ist genau das, was die Startseite zeigen soll.
 *
 * Der Board-Chip „Bei mir" (resources/js/board/components/Board.jsx → isMyWork)
 * lässt die eigene Lieferung im Review dagegen ganz weg: dort steht sie sichtbar in
 * ihrer Spalte, und „Bei mir" soll nur zeigen, was an MIR hängt. Zwei Sichten,
 * dieselbe Regel „Eigenreview ist kein Auftrag" — auf dem Dashboard mit eigener
 * Gruppe, weil die Lieferung projektübergreifend sonst unsichtbar wäre.
 *
 * Warum serverseitig und nicht aus dem geteilten React-Store: der hält immer genau
 * EIN Projekt (resources/js/data/projectStore.js). Projektübergreifend gibt es
 * nichts zu teilen — dasselbe Muster wie {@see UserStatisticsPresenter}.
 *
 * Sichtbarkeit: dieselbe Regel wie Projektliste und Statistik (Organisations-Owner
 * sieht alles, sonst eigene + Team-Projekte). Die Nebenpanels (frei zum Ziehen,
 * Projekte) lassen ARCHIVIERTE Projekte weg — dort gibt es nichts mehr zu holen.
 * Die „Bei mir"-Liste nicht: was mir zugeteilt ist, soll nicht stillschweigend
 * verschwinden, nur weil jemand das Projekt archiviert hat.
 */
class DashboardPresenter
{
    /** Einträge je Gruppe der „Bei mir"-Liste; der Rest wird nur gezählt. */
    private const PER_BUCKET = 20;

    /** Vorschläge im Panel „Frei zum Ziehen". */
    private const PICKABLE = 6;

    /** Projekte im Panel „Meine Projekte". */
    private const PROJECTS = 8;

    /** Zeilen im Aktivitäts-Protokoll. */
    private const ACTIVITY = 12;

    /**
     * @return array<string, mixed>
     */
    public function payload(User $user): array
    {
        $projectIds = $this->visibleProjectIds($user);

        if ($projectIds === []) {
            return $this->emptyPayload();
        }

        $items = $this->actionableItems($user, $projectIds);
        $activeIds = $this->activeProjectIds($projectIds);

        return [
            'hasProjects' => true,
            'buckets' => $this->buckets($items),
            'projectFilters' => $this->projectFilters($items),
            'kpis' => $this->kpis($user, $projectIds, $items),
            'pickable' => $this->pickable($activeIds),
            'projects' => $this->projects($user, $activeIds, $items),
            'activity' => $this->activity($user, $projectIds),
        ];
    }

    /**
     * Projekte, die der Nutzer sehen darf — dieselbe Regel wie in Projektübersicht
     * und Statistik (Org-Owner sieht alle, sonst eigene + Team-Projekte).
     *
     * @return array<int, int>
     */
    private function visibleProjectIds(User $user): array
    {
        if ($user->organization_id === null) {
            return [];
        }

        $isOrgOwner = $user->organization?->isOwner($user) === true;

        return Project::query()
            ->where('organization_id', $user->organization_id)
            ->when(! $isOrgOwner, fn ($q) => $q->where(fn ($inner) => $inner
                ->where('created_by_id', $user->id)
                ->orWhereHas('teams.members', fn ($m) => $m->where('users.id', $user->id))))
            ->pluck('id')
            ->all();
    }

    /**
     * Die sichtbaren, NICHT archivierten Projekte — Bezugsmenge der Nebenpanels.
     *
     * @param  array<int, int>  $projectIds
     * @return array<int, int>
     */
    private function activeProjectIds(array $projectIds): array
    {
        return Project::query()
            ->whereIn('id', $projectIds)
            ->whereNull('archived_at')
            ->pluck('id')
            ->all();
    }

    /**
     * Alles, was Handlungsbedarf bei MIR bedeutet — eine Abfrage über die drei
     * Gruppen, danach in PHP einsortiert (die Gruppe folgt aus dem `kind` des
     * org-konfigurierten Status, den die Abfrage ohnehin mitlädt).
     *
     * @param  array<int, int>  $projectIds
     * @return Collection<int, array<string, mixed>>
     */
    private function actionableItems(User $user, array $projectIds): Collection
    {
        $userId = $user->id;

        $tasks = Task::query()
            ->whereIn('project_id', $projectIds)
            ->where(function (Builder $q) use ($userId) {
                // Eigene Arbeitsschritte und eigene Ausnahmen.
                $q->where(fn (Builder $own) => $own
                    ->where('claimed_by_id', $userId)
                    ->whereHas('orgStatus', fn (Builder $s) => $s->whereIn('kind', ['active', 'exception'])));

                // Reviews: mir zugewiesen ODER noch frei (ein freies Review kann
                // jeder übernehmen). Ob daraus ein holbares Review (`review`) oder
                // die eigene Lieferung (`awaiting`) wird, entscheidet {@see item()}.
                $q->orWhere(fn (Builder $review) => $review
                    ->whereHas('orgStatus', fn (Builder $s) => $s->where('kind', 'review'))
                    ->where(fn (Builder $who) => $who
                        ->where('reviewed_by', $userId)
                        ->orWhereNull('reviewed_by')));

                // Die EIGENE Lieferung im Review — unabhängig davon, wer sie reviewt.
                // Ohne diesen Zweig fiel sie aus der Liste, sobald ein fremder
                // Reviewer eingetragen war: dann greift weder der Arbeitsschritt-Zweig
                // (der Status ist `review`) noch der Review-Zweig (reviewed_by ist
                // weder ich noch leer). Genau das ist aber der Normalfall von „wartet
                // auf Review", und meine Lieferung darf nicht unsichtbar werden, nur
                // weil sich jemand ihrer angenommen hat.
                $q->orWhere(fn (Builder $mine) => $mine
                    ->whereHas('orgStatus', fn (Builder $s) => $s->where('kind', 'review'))
                    ->where(fn (Builder $who) => $who
                        ->where('claimed_by_id', $userId)
                        ->orWhere('created_by_id', $userId)));
            })
            ->with([
                'project:id,alias,name,github_repo',
                'orgStatus',
                'phase:id,name',
                'creator:id,name',
                'claimer:id,name',
                'reviewer:id,name',
                'concern:id,task_id,summary',
            ])
            ->get();

        return $tasks
            ->map(fn (Task $task) => $this->item($task, $userId))
            ->filter()
            ->sortBy('sinceTs')
            ->values();
    }

    /**
     * Eine Zeile der „Bei mir"-Liste. `null`, wenn der Status keiner der vier
     * Gruppen zuzuordnen ist (z. B. ein Status ohne `kind`) — dann wäre die Zeile
     * auf dem Dashboard nicht erklärbar.
     *
     * Ein Review an EIGENER Arbeit (ich habe den Task erstellt oder beansprucht)
     * wird zu `awaiting`: reviewen darf ich es nicht, aber es ist meine Lieferung,
     * die auf einen Reviewer wartet.
     *
     * @return array<string, mixed>|null
     */
    private function item(Task $task, int $userId): ?array
    {
        $status = $task->orgStatus;
        $isOwnWork = $task->created_by_id === $userId || $task->claimed_by_id === $userId;
        $bucket = match ($status?->kind) {
            'active' => 'work',
            'review' => $isOwnWork ? 'awaiting' : 'review',
            'exception' => 'blocked',
            default => null,
        };

        if ($bucket === null) {
            return null;
        }

        // „Seit wann liegt das hier": beim eigenen Arbeitsschritt der Claim, sonst
        // die letzte Änderung am Task (einen Status-Zeitstempel gibt es nicht).
        $since = $bucket === 'work'
            ? ($task->claimed_at ?? $task->updated_at)
            : $task->updated_at;

        $repo = $task->project?->githubRepo();

        return [
            'id' => $task->id,
            'bucket' => $bucket,
            'name' => $task->name,
            'summary' => $task->summary,
            'sp' => $task->effort_story_points,
            'criticality' => $task->criticality?->value,
            'projectId' => $task->project_id,
            'projectAlias' => $task->project?->alias,
            'projectName' => $task->project?->name,
            'phase' => $task->phase?->name,
            'url' => $task->project !== null
                ? route('projects.tasks.show', [$task->project, $task->id])
                : null,
            'boardUrl' => $task->project !== null ? route('projects.show', $task->project) : null,
            'statusLabel' => $status !== null ? $this->statusLabel($status) : null,
            'statusBadge' => StatusPalette::badge($status?->color_token),
            'prNumber' => $task->pr_number,
            'prUrl' => $task->pr_number !== null && $repo !== null
                ? "https://github.com/{$repo}/pull/{$task->pr_number}"
                : null,
            // CI/Threads sind nur aussagekräftig, wenn der PR-Status je geholt
            // wurde (planstack:sync-pr-status) — sonst null statt 0.
            'ciFailed' => $task->pr_status_synced_at !== null ? (int) $task->pr_ci_failed : null,
            'openThreads' => $task->pr_status_synced_at !== null ? (int) $task->pr_unresolved_threads : null,
            'reviewRecommendation' => $task->last_review_recommendation?->value,
            'concern' => $task->concern?->summary,
            // Ersteller und (potenzieller) Reviewer stehen an JEDER Zeile: an wem
            // ein Task hängt, ist die Frage, die man vor dem Klick hat — und beim
            // Review erklärt der Ersteller, warum eine Zeile in `awaiting` steht.
            'creatorName' => $task->creator?->name,
            'claimerName' => $task->claimer?->name,
            'reviewerName' => $task->reviewer?->name,
            'isFreeReview' => $bucket === 'review' && $task->reviewed_by === null,
            'isMyClaim' => $task->claimed_by_id === $userId,
            'sinceAt' => $since?->toIso8601String(),
            'sinceTs' => $since?->getTimestamp() ?? 0,
        ];
    }

    /**
     * Die Gruppen der Liste, je mit gekappten Einträgen plus wahrer Anzahl —
     * eine org-weite Review-Warteschlange kann lang sein, die Seite soll trotzdem
     * eine Seite bleiben.
     *
     * `byProject` hält Anzahl und SP je Projekt-Kürzel. Die Filter-Pills rechnen
     * damit statt aus den gelieferten Einträgen: bei gekappter Liste wären die
     * clientseitig gezählten Werte zu niedrig.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buckets(Collection $items): array
    {
        $order = ['work', 'review', 'awaiting', 'blocked'];
        $grouped = $items->groupBy('bucket');

        return collect($order)
            ->map(function (string $key) use ($grouped) {
                $group = $grouped->get($key, collect());

                return [
                    'key' => $key,
                    'count' => $group->count(),
                    'sp' => (int) $group->sum('sp'),
                    'byProject' => $group
                        ->groupBy('projectAlias')
                        ->map(fn (Collection $perProject) => [
                            'count' => $perProject->count(),
                            'sp' => (int) $perProject->sum('sp'),
                        ])
                        ->all(),
                    'items' => $group->take(self::PER_BUCKET)
                        ->map(fn (array $item) => collect($item)->except('sinceTs')->all())
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * Projekte, in denen etwas bei mir liegt — Grundlage der Filter-Pills über der
     * „Bei mir"-Liste. Gezählt wird über ALLE Einträge, nicht nur die je Gruppe
     * angezeigten, damit die Pille die Wahrheit sagt. Projekte ohne Handlungsbedarf
     * bekommen keine Pille: ein Filter, der garantiert eine leere Liste liefert,
     * ist keine Hilfe.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function projectFilters(Collection $items): array
    {
        return $items
            ->groupBy('projectAlias')
            ->map(fn (Collection $group, string $alias) => [
                'alias' => $alias,
                'name' => $group->first()['projectName'],
                'count' => $group->count(),
            ])
            ->sortBy([['count', 'desc'], ['alias', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Kopfzahlen. „Diese Woche" zählt ab Wochenbeginn: gelieferte eigene Tasks
     * (merged_at) und gegebene Reviews (last_reviewed_at) — zwei Aggregate, keine
     * geladenen Rows.
     *
     * Der Merge-Zeitpunkt kommt hier NUR aus `merged_at`. Die Statistik-Seite
     * weicht ersatzweise auf die PR-Daten aus; für eine Wochenkachel wäre der
     * zusätzliche Join den Aufwand nicht wert.
     *
     * @param  array<int, int>  $projectIds
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function kpis(User $user, array $projectIds, Collection $items): array
    {
        $weekStart = CarbonImmutable::now()->startOfWeek();

        $delivered = Task::query()
            ->whereIn('project_id', $projectIds)
            ->where('claimed_by_id', $user->id)
            ->where('merged_at', '>=', $weekStart)
            ->selectRaw('count(*) as cnt, coalesce(sum(effort_story_points), 0) as sp')
            ->first();

        $reviewsGiven = Task::query()
            ->whereIn('project_id', $projectIds)
            ->where('reviewed_by', $user->id)
            ->where('last_reviewed_at', '>=', $weekStart)
            ->count();

        // Nur die Gruppe `review` — die eigene Arbeit im Review (`awaiting`) ist
        // kein Review, das ich holen oder abschließen könnte.
        $reviews = $items->where('bucket', 'review');
        $oldestTs = $items->min('sinceTs');

        return [
            'actionable' => $items->count(),
            'actionableSp' => (int) $items->sum('sp'),
            'reviewsFree' => $reviews->where('isFreeReview', true)->count(),
            'reviewsMine' => $reviews->where('isFreeReview', false)->count(),
            'deliveredTasks' => (int) ($delivered->cnt ?? 0),
            'deliveredSp' => (int) ($delivered->sp ?? 0),
            'reviewsGiven' => $reviewsGiven,
            'oldestDays' => $oldestTs ? (time() - $oldestTs) / 86400 : null,
        ];
    }

    /**
     * „Frei zum Ziehen": unbeanspruchte Tasks in einem Warte-Status, deren Gate
     * offen ist. Der Gate-Split läuft — wie in {@see ProjectOverviewPresenter} —
     * über eine Relations-Subquery statt über per-Task-PHP: eine Voraussetzung
     * gilt als erfüllt, sobald sie einen PR trägt oder in einem „done"-Status steht.
     *
     * Sortiert nach der Zahl direkt abhängiger Tasks — was am meisten aufhält,
     * steht oben (dieselbe Absicht wie POST /claim-next).
     *
     * @param  array<int, int>  $projectIds
     * @return array<int, array<string, mixed>>
     */
    private function pickable(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $undelivered = fn (Builder $q) => $q->whereNull('pr_number')
            ->whereDoesntHave('orgStatus', fn (Builder $s) => $s->where('counts_as_done', true));

        return Task::query()
            ->whereIn('project_id', $projectIds)
            ->whereNull('claimed_by_id')
            ->whereNull('pr_number')
            ->whereHas('orgStatus', fn (Builder $s) => $s->where('kind', 'waiting'))
            ->whereDoesntHave('prerequisites', $undelivered)
            ->withCount('dependents')
            ->with(['project:id,alias,name', 'orgStatus', 'phase:id,name'])
            ->orderByDesc('dependents_count')
            ->orderBy('id')
            ->limit(self::PICKABLE)
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'name' => $task->name,
                'summary' => $task->summary,
                'sp' => $task->effort_story_points,
                'phase' => $task->phase?->name,
                'projectAlias' => $task->project?->alias,
                'projectName' => $task->project?->name,
                'dependents' => (int) $task->dependents_count,
                'statusLabel' => $task->orgStatus !== null ? $this->statusLabel($task->orgStatus) : null,
                'statusBadge' => StatusPalette::badge($task->orgStatus?->color_token),
                'url' => $task->project !== null
                    ? route('projects.tasks.show', [$task->project, $task->id])
                    : null,
            ])
            ->all();
    }

    /**
     * Kompakte Projektzeile: Fortschritt (SP, sonst Tasks) plus meine offenen und
     * meine anstehenden Posten. Sortiert nach Handlungsbedarf — das Projekt, das
     * mich braucht, steht oben.
     *
     * @param  array<int, int>  $projectIds
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function projects(User $user, array $projectIds, Collection $items): array
    {
        if ($projectIds === []) {
            return [];
        }

        $actionableByProject = $items->groupBy('projectId')->map->count();
        $isDone = fn (Builder $s) => $s->where('counts_as_done', true);

        return Project::query()
            ->whereIn('id', $projectIds)
            ->withCount('tasks')
            ->withCount(['tasks as done_count' => fn (Builder $q) => $q->whereHas('orgStatus', $isDone)])
            ->withCount(['tasks as my_open_count' => fn (Builder $q) => $q
                ->where('claimed_by_id', $user->id)
                ->whereDoesntHave('orgStatus', $isDone)])
            ->withSum('tasks as total_sp', 'effort_story_points')
            ->withSum(['tasks as done_sp' => fn (Builder $q) => $q->whereHas('orgStatus', $isDone)], 'effort_story_points')
            ->get()
            ->map(function (Project $project) use ($actionableByProject) {
                $totalSp = (int) $project->total_sp;
                $doneSp = (int) $project->done_sp;
                $tasks = (int) $project->tasks_count;

                // Fortschritt nach SP, solange es welche gibt — sonst nach Anzahl
                // Tasks. Ein Projekt ohne Schätzung soll keinen 0-%-Balken zeigen.
                $percent = match (true) {
                    $totalSp > 0 => round($doneSp / $totalSp * 100),
                    $tasks > 0 => round((int) $project->done_count / $tasks * 100),
                    default => 0,
                };

                return [
                    'alias' => $project->alias,
                    'name' => $project->name,
                    'url' => route('projects.show', $project),
                    'tasksCount' => $tasks,
                    'doneCount' => (int) $project->done_count,
                    'percent' => (int) $percent,
                    'byStoryPoints' => $totalSp > 0,
                    'myOpen' => (int) $project->my_open_count,
                    'myActionable' => (int) ($actionableByProject[$project->id] ?? 0),
                ];
            })
            ->sortBy([
                ['myActionable', 'desc'],
                ['myOpen', 'desc'],
                ['percent', 'desc'],
            ])
            ->take(self::PROJECTS)
            ->values()
            ->all();
    }

    /**
     * Was zuletzt passiert ist — aus dem Fortschritts-Protokoll (`task_events`,
     * gemeldet vom /planstack-Skill). Bewusst NICHT der Audit-Changelog: der ist
     * pro Projekt aufgebaut und für eine Randspalte zu schwer.
     *
     * @param  array<int, int>  $projectIds
     * @return array<int, array<string, mixed>>
     */
    private function activity(User $user, array $projectIds): array
    {
        return TaskEventLog::query()
            ->whereHas('task', fn (Builder $q) => $q->whereIn('project_id', $projectIds))
            ->with(['task:id,name,project_id', 'task.project:id,alias', 'actor:id,name'])
            ->orderByDesc('id')
            ->limit(self::ACTIVITY)
            ->get()
            ->map(fn (TaskEventLog $log) => [
                'id' => $log->id,
                'event' => $log->event->value,
                'label' => $log->event->label(),
                'group' => $log->event->group(),
                'taskName' => $log->task?->name,
                'projectAlias' => $log->task?->project?->alias,
                'url' => $log->task?->project !== null
                    ? route('projects.tasks.show', [$log->task->project, $log->task->id])
                    : null,
                'actorName' => $log->actor?->name,
                'isMe' => $log->actor_id === $user->id,
                'at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
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
            'hasProjects' => false,
            'buckets' => array_map(
                fn (string $key) => ['key' => $key, 'count' => 0, 'sp' => 0, 'byProject' => [], 'items' => []],
                ['work', 'review', 'awaiting', 'blocked'],
            ),
            'projectFilters' => [],
            'kpis' => [
                'actionable' => 0, 'actionableSp' => 0,
                'reviewsFree' => 0, 'reviewsMine' => 0,
                'deliveredTasks' => 0, 'deliveredSp' => 0, 'reviewsGiven' => 0,
                'oldestDays' => null,
            ],
            'pickable' => [],
            'projects' => [],
            'activity' => [],
        ];
    }
}
