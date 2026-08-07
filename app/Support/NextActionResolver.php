<?php

namespace App\Support;

use App\Enums\ReviewRecommendation;
use App\Enums\StatusRole;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Entscheidet server-seitig die nächste sinnvolle Aktion für einen Worker und
 * reserviert den betroffenen Task atomar — die Grundlage für „/planstack auto":
 * der Aufrufer kennt {action, task} schon beim Start des Subagents (Name z. B.
 * „fix RPm-req") und muss die Entscheidung nicht selbst treffen.
 *
 * Priorität: fix → review → work (Blockiertes zuerst freiräumen).
 *  - fix:    offener PR mit rotem CI / offenen Review-Threads / angeforderten
 *            Änderungen (GitHub reviewDecision oder Planstack-Review). Reserviert
 *            per Lease (fix_leased_by/…_expires_at), da fix keine natürliche
 *            Reservierung hat. Eigene Tasks eingeschlossen.
 *  - review: Task im Review-Pool mit PR, noch ohne Reviewer, nicht der eigene —
 *            reserviert per reviewed_by (wie review-next).
 *  - work:   bester pickbarer Task (meiste unlocks) — reserviert per claim (wie
 *            claim-next), inkl. CLAIMED-Statuswechsel + Board-Broadcast.
 *
 * Alle Reservierungen sind bedingte UPDATEs → parallele Worker kollidieren nicht
 * (genau einer gewinnt, die anderen fallen auf den nächsten Kandidaten).
 *
 * **Mehrere Arbeitseinheiten auf einmal** ({@see resolveMany()}): ein Supervisor mit
 * N parallelen Workern holt sich alle N Einheiten in EINEM Aufruf. Das ist nicht nur
 * billiger als N Roundtrips — es schließt auch die Lücke zwischen zwei Aufrufen, in
 * der zwei Worker denselben Task bekommen könnten. Drei Vorkehrungen greifen dabei
 * ineinander:
 *
 *  1. **Ausschluss innerhalb des Stapels** ($exclude): ein in dieser Runde bereits
 *     vergebener Task kommt nicht erneut in Frage, auch wenn die (memoisierte)
 *     Kandidatenliste ihn noch als frei führt.
 *  2. **Lebende Bearbeitung respektieren** ({@see ClaimSession::isActivelyWorked()}):
 *     ein Task, an dem sichtbar gerade gearbeitet wird, wird übersprungen. Nötig, weil
 *     das `fix`-Lease nach 15 Minuten abläuft, die Arbeit daran aber nicht — ohne
 *     diese Prüfung bekäme ein zweiter Worker denselben PR unter den Händen weg.
 *  3. **Worker-Label beim Reservieren** (`#<slot>`): die Reservierung wird mit dem
 *     Label des Workers gestempelt, der sie ausführen soll — nicht mit dem des
 *     Supervisors, der den Aufruf macht. Nur so trifft der Heartbeat des Workers
 *     später sein eigenes Lease, und das Board zeigt ab der ersten Sekunde, welcher
 *     Slot an welchem Task sitzt.
 */
class NextActionResolver
{
    /** Rote CI-Zustände des GitHub statusCheckRollup. */
    private const CI_RED = ['FAILURE', 'ERROR'];

    /**
     * Memo je Auflösungs-Stapel: das Board ist der teure Teil (Gates, unlocks,
     * Pickbarkeit über alle Tasks). Für N Einheiten wird es einmal berechnet statt
     * N-mal — die Pickbarkeit ändert sich durch einen Claim nicht (erst ein PR öffnet
     * Gates), und der bereits vergebene Task fällt über $exclude heraus.
     *
     * @var array<int, Collection<int, Task>>
     */
    private array $boardMemo = [];

    /** @var array<int, array<int, int>> */
    private array $doneStatusMemo = [];

    /** @var array<int, array<int, int>> */
    private array $reviewPoolMemo = [];

    public function __construct(
        private readonly TaskBoardService $board,
        private readonly TaskStatusService $statuses,
    ) {}

    /**
     * Eine Arbeitseinheit bestimmen und reservieren.
     *
     * @param  array<int, int>  $exclude  Task-ids, die in diesem Stapel schon vergeben sind
     * @param  int  $slot  Worker-Slot (1-basiert) — wird Teil des Session-Labels
     * @return array{action: 'fix'|'review'|'work'|'none', task: ?Task, reason: ?string, session: ?string}
     */
    public function resolve(Project $project, User $user, array $exclude = [], int $slot = 1): array
    {
        return $this->tryFix($project, $user, $exclude, $slot)
            ?? $this->tryReview($project, $user, $exclude, $slot)
            ?? $this->tryWork($project, $user, $exclude, $slot)
            ?? ['action' => 'none', 'task' => null, 'reason' => null, 'session' => null];
    }

    /**
     * Bis zu $count Arbeitseinheiten für ebenso viele parallele Worker bestimmen und
     * reservieren — in einem Durchgang, jede Einheit auf einem eigenen Task.
     *
     * Liefert weniger Einheiten als angefragt, wenn nicht genug Arbeit ansteht (leeres
     * Array = nichts zu tun). Die Reihenfolge folgt derselben Priorität wie bei einer
     * einzelnen Einheit, es kommen also erst die `fix`-Fälle, dann `review`, dann `work`.
     *
     * @return array<int, array{action: 'fix'|'review'|'work', task: Task, reason: ?string, session: string}>
     */
    public function resolveMany(Project $project, User $user, int $count): array
    {
        // Frischer Stapel, frisches Memo: der Resolver kann (theoretisch) mehrfach im
        // selben Prozess benutzt werden, und ein Board von vorhin wäre dann falsch.
        $this->boardMemo = [];
        $this->doneStatusMemo = [];
        $this->reviewPoolMemo = [];

        $units = [];
        $exclude = [];

        for ($slot = 1; $slot <= max(1, $count); $slot++) {
            $unit = $this->resolve($project, $user, $exclude, $slot);

            if ($unit['action'] === 'none' || $unit['task'] === null) {
                break;
            }

            $exclude[] = $unit['task']->id;
            $units[] = $unit;
        }

        return $units;
    }

    /**
     * @param  array<int, int>  $exclude
     * @return array{action: 'fix', task: Task, reason: string, session: string}|null
     */
    private function tryFix(Project $project, User $user, array $exclude, int $slot): ?array
    {
        $doneStatusIds = $this->doneStatusIds($project);
        $now = now();

        $candidates = $project->tasks()
            ->whereNotNull('pr_number')
            ->when($exclude !== [], fn ($q) => $q->whereNotIn('id', $exclude))
            ->when($doneStatusIds !== [], fn ($q) => $q->whereNotIn('status_id', $doneStatusIds))
            ->where(fn ($q) => $q
                ->whereIn('pr_ci_status', self::CI_RED)
                ->orWhere('pr_unresolved_threads', '>', 0)
                ->orWhere('pr_review_decision', 'CHANGES_REQUESTED')
                ->orWhere('last_review_recommendation', ReviewRecommendation::REQUEST_CHANGES->value))
            // Lease frei oder abgelaufen.
            ->where(fn ($q) => $q->whereNull('fix_leased_by')->orWhere('fix_lease_expires_at', '<', $now))
            // … und niemand arbeitet sichtbar daran (s. Klassen-Doku, Punkt 2).
            ->where($this->notActivelyWorked())
            // Ältester Stand des letzten Commits zuerst; PRs ohne Commit-Datum ans Ende.
            ->orderByRaw('pr_last_commit_at is null, pr_last_commit_at asc')
            ->orderBy('id')
            ->get();

        $ttl = max(1, (int) config('planstack.fix_lease_minutes', 15));

        foreach ($candidates as $candidate) {
            $label = $this->sessionLabel('fix', $project, $candidate, $slot);

            // Atomares Lease: nur greifen, wenn frei/abgelaufen. Query-Builder-Update
            // umgeht Model-Events (das Lease ist kein Board-Status, kein Broadcast) —
            // der Vermerk „Worker #k arbeitet daran" gehört dagegen aufs Board, also
            // wird er unten explizit gesendet.
            $leased = Task::whereKey($candidate->id)
                ->where(fn ($q) => $q->whereNull('fix_leased_by')->orWhere('fix_lease_expires_at', '<', $now))
                ->where($this->notActivelyWorked())
                ->update([
                    'fix_leased_by' => $user->id,
                    'fix_lease_expires_at' => $now->copy()->addMinutes($ttl),
                    ...$this->activeSessionAttrs($label, $user, $now),
                ]);

            if ($leased === 1) {
                $candidate->setRelation('project', $project);
                $candidate->emitEntityChange('update');

                return [
                    'action' => 'fix',
                    'task' => $candidate,
                    'reason' => $this->fixReason($candidate),
                    'session' => $label,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<int, int>  $exclude
     * @return array{action: 'review', task: Task, reason: null, session: string}|null
     */
    private function tryReview(Project $project, User $user, array $exclude, int $slot): ?array
    {
        $poolIds = $this->reviewPoolStatusIds($project);
        if ($poolIds === []) {
            return null;
        }

        $uid = $user->id;
        $now = now();

        $candidates = $project->tasks()
            ->whereIn('status_id', $poolIds)
            ->when($exclude !== [], fn ($q) => $q->whereNotIn('id', $exclude))
            ->whereNotNull('pr_number')
            ->whereNull('reviewed_by')
            // Eigene Tasks nicht zum Review picken.
            ->where(fn ($q) => $q->whereNull('claimed_by_id')->orWhere('claimed_by_id', '!=', $uid))
            // Kein laufender Fix: wird der PR gerade repariert, ist ein Review darauf
            // verschwendet — es bewertet einen Stand, den es in Minuten nicht mehr gibt.
            ->where(fn ($q) => $q->whereNull('fix_leased_by')->orWhere('fix_lease_expires_at', '<', $now))
            // Niemand arbeitet sichtbar daran (z. B. ein fix ohne Lease-Eintrag).
            ->where($this->notActivelyWorked())
            ->orderBy('id')
            ->get();

        foreach ($candidates as $candidate) {
            $label = $this->sessionLabel('review', $project, $candidate, $slot);

            $claimed = Task::whereKey($candidate->id)
                ->whereNull('reviewed_by')
                ->where($this->notActivelyWorked())
                ->update([
                    'reviewed_by' => $uid,
                    ...$this->activeSessionAttrs($label, $user, $now),
                ]);

            if ($claimed === 1) {
                $candidate->setRelation('project', $project);
                $candidate->emitEntityChange('update');

                return ['action' => 'review', 'task' => $candidate, 'reason' => null, 'session' => $label];
            }
        }

        return null;
    }

    /**
     * @param  array<int, int>  $exclude
     * @return array{action: 'work', task: Task, reason: null, session: string}|null
     */
    private function tryWork(Project $project, User $user, array $exclude, int $slot): ?array
    {
        $candidates = $this->boardTasks($project)
            ->filter(fn ($t) => $t->x_pickable)
            ->reject(fn ($t) => in_array($t->id, $exclude, true))
            // Ein unbeanspruchter Task, an dem noch sichtbar gearbeitet wird (gerade
            // freigegeben, Worker noch dran), bleibt für diese Runde außen vor.
            ->reject(fn ($t) => ClaimSession::isActivelyWorked($t))
            ->sortByDesc('x_unlocks')
            ->values();

        $claimedStatus = $project->organization?->statusForRole(StatusRole::CLAIMED);
        $now = now();

        foreach ($candidates as $candidate) {
            $label = $this->sessionLabel('work', $project, $candidate, $slot);

            $attrs = $claimedStatus !== null
                ? $this->statuses->attributesFor($candidate, $claimedStatus, $user)
                : $this->statuses->withClaimSession(
                    ['claimed_by_id' => $user->id, 'claimed_at' => now(), 'status' => TaskStatus::CLAIMED->value]
                );

            // Claim-Lease und Vermerk tragen das Label des WORKERS, nicht das des
            // Supervisors aus dem Request-Header: sonst hält laut Board eine Session
            // den Task, die nie wieder etwas von sich hören lässt — und der Heartbeat
            // des Workers (gleicher Nutzer, anderes Label) würde nie greifen.
            //
            // Nur wenn dieser Statuswechsel wirklich claimt: räumen die On-Enter-Effekte
            // der Org den Assignee (frei konfigurierbar), gehört auch kein Lease dran.
            if (($attrs['claimed_by_id'] ?? null) !== null) {
                $attrs['claim_session_label'] = $label;
                $attrs['claim_seen_at'] = $now;
            }

            $attrs = [...$attrs, ...$this->activeSessionAttrs($label, $user, $now)];

            $claimed = Task::whereKey($candidate->id)
                ->whereNull('claimed_by_id')
                ->whereNull('pr_number')
                ->update($attrs);

            if ($claimed === 1) {
                $candidate->setRelation('project', $project);
                $candidate->emitEntityChange('update');

                return ['action' => 'work', 'task' => $candidate, 'reason' => null, 'session' => $label];
            }
        }

        return null;
    }

    /**
     * Das Session-Label des Workers, der diese Einheit ausführen soll:
     * `<aktion> <ALIAS>/<TASK> #<slot>`.
     *
     * Erstes Wort = das laufende Sub-Kommando (die Middleware liefert daran die
     * passende Kommando-Anleitung aus), Slot-Nummer am Ende = der Worker im Pool.
     * Auf 60 Zeichen gekürzt, damit der Wert exakt dem entspricht, was der Worker
     * anschließend im Header sendet ({@see ClaimSession::set()} kürzt genauso) —
     * sonst fände der Heartbeat sein eigenes Lease nicht wieder.
     */
    private function sessionLabel(string $action, Project $project, Task $task, int $slot): string
    {
        return mb_substr("{$action} {$project->alias}/{$task->name} #{$slot}", 0, 60);
    }

    /**
     * Der Vermerk „dieser Worker arbeitet daran" — schon beim Reservieren gesetzt,
     * nicht erst beim ersten eigenen Aufruf des Workers. Damit ist der Task für
     * jeden weiteren Aufruf von {@see resolveMany()} sichtbar belegt (und auf dem
     * Board ist zu sehen, wohin die Einheit gegangen ist), bevor der Worker
     * überhaupt gestartet ist.
     *
     * @return array<string, mixed>
     */
    private function activeSessionAttrs(string $label, User $user, \DateTimeInterface $now): array
    {
        return [
            'active_session_label' => ClaimSession::withInitials($label, $user),
            'active_session_seen_at' => $now,
        ];
    }

    /**
     * Bedingung „an diesem Task arbeitet gerade sichtbar niemand" für Query-Builder —
     * das SQL-Gegenstück zu {@see ClaimSession::isActivelyWorked()}.
     */
    private function notActivelyWorked(): \Closure
    {
        $cutoff = now()->subMinutes(ClaimSession::activeTtlMinutes());

        return fn ($q) => $q
            ->whereNull('active_session_label')
            ->orWhereNull('active_session_seen_at')
            ->orWhere('active_session_seen_at', '<', $cutoff);
    }

    /**
     * Kurzbegründung, warum ein Task zum Fix ansteht (für Logs / Subagent-Name).
     */
    private function fixReason(Task $task): string
    {
        $reasons = [];

        if (in_array($task->pr_ci_status, self::CI_RED, true)) {
            $reasons[] = 'CI '.$task->pr_ci_status;
        }
        if (($task->pr_unresolved_threads ?? 0) > 0) {
            $reasons[] = $task->pr_unresolved_threads.' unresolved threads';
        }
        if ($task->pr_review_decision === 'CHANGES_REQUESTED') {
            $reasons[] = 'changes requested';
        } elseif ($task->last_review_recommendation === ReviewRecommendation::REQUEST_CHANGES) {
            $reasons[] = 'review: changes requested';
        }

        return implode(', ', $reasons) ?: 'needs fix';
    }

    /**
     * Das (memoisierte) Board dieses Projekts — siehe $boardMemo.
     *
     * @return Collection<int, Task>
     */
    private function boardTasks(Project $project): Collection
    {
        return $this->boardMemo[$project->id] ??= $this->board->board($project);
    }

    /**
     * Status-IDs der Org, die als „erledigt" gelten (Rolle MERGED/COMPLETED) —
     * solche Tasks kommen nicht mehr für einen Fix in Frage.
     *
     * @return array<int, int>
     */
    private function doneStatusIds(Project $project): array
    {
        return $this->doneStatusMemo[$project->id] ??= $project->organization?->statuses()
            ->whereIn('role', [StatusRole::MERGED->value, StatusRole::COMPLETED->value])
            ->pluck('id')
            ->all() ?? [];
    }

    /**
     * Status-IDs des Review-Pools der Org.
     *
     * @return array<int, int>
     */
    private function reviewPoolStatusIds(Project $project): array
    {
        return $this->reviewPoolMemo[$project->id] ??= $project->organization?->reviewPoolStatusIds() ?? [];
    }
}
