<?php

namespace App\Http\Resources;

use App\Http\Middleware\AttachPlanstackConfig;
use App\Models\Task;
use App\Support\TaskBoardService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 *
 * Consistent JSON for a Task, including the computed board fields
 * (gate, stacking, pickable, unlocks, pr_url) that are present when the
 * task has been decorated by {@see TaskBoardService}.
 *
 * The field set is trimmed by the per-project `task.fields` config
 * (minimal | standard | full) — a server-enforced token knob the client
 * never has to ask about.
 */
class TaskResource extends JsonResource
{
    /** Keys kept for `task.fields=minimal` — just enough to pick & work a task. */
    private const MINIMAL = [
        'id', 'name', 'summary', 'acceptance_criteria', 'status',
        'pickable', 'unlocks', 'gate', 'unmet',
    ];

    /**
     * When true, `pr_number`/`pr_url` are always included, even under
     * `task.fields=minimal`. Set for review responses, which must carry the PR
     * regardless of the project's token-saving field config.
     */
    public bool $alwaysIncludePr = false;

    /** Extra keys added for `task.fields=standard`. */
    private const STANDARD_EXTRA = [
        'display_status', 'phase_id', 'effort', 'pr_number', 'pr_url',
        'pr_ci_status', 'pr_ci_failed', 'pr_ci_running', 'pr_ci_success', 'pr_ci_waiting',
        'pr_in_merge_queue', 'pr_merge_queue_state', 'pr_mergeable', 'pr_unresolved_threads', 'pr_review_decision',
        'claimed_by_id', 'prerequisites', 'concern', 'stacking',
        // Welche Worker-Session hält den Task und wann war sie zuletzt zu sehen —
        // gehört zum Claim-Zustand: ohne beides sieht ein Client einen fremden
        // Claim, aber nicht, ob dahinter noch etwas läuft.
        'claim_session', 'claim_seen_at',
        // Welche Session gerade daran arbeitet — auch ohne Claim (fix/review).
        'active_session', 'active_session_seen_at',
        // Zuletzt gemeldeter Fortschritt (Event mit detail/progress) — gehört zum
        // Arbeitszustand: zeigt auf der Karte, wie weit ein laufender Worker ist.
        'progress_detail', 'progress_percent', 'progress_at',
        // Reviewer gehört zum Review-Zustand wie last_review_* — ohne ihn kann ein
        // Client nicht erkennen, dass ein Review (auch sein eigener) schon läuft.
        'reviewed_by', 'reviewed_by_name',
        'last_reviewed_at', 'last_review_recommendation', 'last_review_summary',
        'target_actual', 'test_cases', 'criticality', 'criticality_label',
        'custom_fields',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $full = $this->fullArray($request);

        // Request-Override: ?fields=minimal|standard|full erzwingt den Feldumfang
        // unabhängig vom Projekt-Knopf `task.fields` — so lassen sich die vollen
        // Details eines Tasks gezielt abrufen, egal wie sparsam das Projekt steht.
        $override = $request->query('fields');
        $fields = in_array($override, ['minimal', 'standard', 'full'], true)
            ? $override
            : AttachPlanstackConfig::value($request, 'task.fields');

        $result = match ($fields) {
            'minimal' => array_intersect_key($full, array_flip(self::MINIMAL)),
            'standard' => array_intersect_key(
                $full,
                array_flip([...self::MINIMAL, ...self::STANDARD_EXTRA]),
            ),
            default => $full,
        };

        // Review-Antworten tragen die PR immer mit — unabhängig von task.fields.
        if ($this->alwaysIncludePr) {
            $result['pr_number'] = $full['pr_number'];
            $result['pr_url'] = $full['pr_url'];
        }

        return $result;
    }

    /**
     * The full, un-trimmed representation.
     *
     * @return array<string, mixed>
     */
    private function fullArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'summary' => $this->summary,
            'description' => $this->description,
            'acceptance_criteria' => $this->description_acceptance_criteria,
            'target_actual' => $this->description_target_actual,
            'test_cases' => $this->description_test_cases,
            'criticality' => $this->criticality?->value,
            'criticality_label' => $this->criticality?->label(),
            // status may be null when the task sits in a custom (org-defined)
            // status with no canonical ENUM value — fall back to the org status
            // key/label (status_id is the authority).
            'status' => $this->status?->value ?? $this->orgStatus?->key,
            'status_label' => $this->status?->label() ?? $this->orgStatus?->label,
            'display_status' => $this->displayStatusKey(),
            'display_status_label' => $this->displayStatusLabel(),
            'project_id' => $this->project_id,
            'phase_id' => $this->phase_id,
            'phase' => $this->whenLoaded('phase', fn () => [
                'id' => $this->phase?->id,
                'name' => $this->phase?->name,
                'position' => $this->phase?->position,
            ]),
            'effort' => [
                'man_days' => $this->effort_man_days !== null ? (float) $this->effort_man_days : null,
                'story_points' => $this->effort_story_points,
                'tokens' => $this->effort_tokens,
            ],
            'affected_files' => $this->affected_files,
            'custom_fields' => $this->custom_fields ?? null,
            'pr_number' => $this->pr_number,
            'pr_url' => $this->x_pr_url ?? null,
            // Von GitHub gepollter PR-Zustand (planstack:sync-pr-status): CI-Rollup,
            // Anzahl unresolved Review-Threads, Review-Entscheidung. Für die
            // Board-Karte (CI-Icon + offene Kommentare).
            'pr_ci_status' => $this->pr_ci_status,
            'pr_ci_failed' => $this->pr_ci_failed,
            'pr_ci_running' => $this->pr_ci_running,
            'pr_ci_success' => $this->pr_ci_success,
            'pr_ci_waiting' => $this->pr_ci_waiting,
            'pr_in_merge_queue' => $this->pr_in_merge_queue !== null ? (bool) $this->pr_in_merge_queue : null,
            'pr_merge_queue_state' => $this->pr_merge_queue_state,
            'pr_mergeable' => $this->pr_mergeable,
            'pr_unresolved_threads' => $this->pr_unresolved_threads,
            'pr_review_decision' => $this->pr_review_decision,
            // Zuletzt per Fortschritts-Event gemeldeter Stand innerhalb der laufenden
            // Phase (`POST /events` mit detail/progress). `progress_at` gehört dazu:
            // ohne den Zeitpunkt liest ein Betrachter einen längst überholten Stand
            // wie einen aktuellen.
            'progress_detail' => $this->progress_detail,
            'progress_percent' => $this->progress_percent,
            'progress_at' => $this->progress_at,
            // Wann der PR-Zustand zuletzt von GitHub geholt wurde. Die Auswertungen
            // brauchen das, um „0 rote CI-Läufe" von „nie gemessen" zu unterscheiden
            // — ohne Sync sähe fehlende Datenbasis wie ein gutes Ergebnis aus.
            'pr_status_synced_at' => $this->pr_status_synced_at,
            // Aggregierte Ist-Kennzahlen der (gesyncten) Pull-Requests — Grundlage
            // der Kalibrierung (Ist-Dateien vs. Schätzung affected_files). Nur wenn
            // die Relation geladen ist; null, wenn die Task keine PRs hat.
            'pr_stats' => $this->whenLoaded('pullRequests', function () {
                $prs = $this->pullRequests;
                if ($prs->isEmpty()) {
                    return null;
                }

                return [
                    'changed_files' => (int) $prs->sum('changed_files'),
                    'additions' => (int) $prs->sum('additions'),
                    'deletions' => (int) $prs->sum('deletions'),
                    'commits' => (int) $prs->sum('commits'),
                    'merged_at' => $prs->pluck('merged_at')->filter()->max(),
                    'updated_at' => $prs->pluck('updated_at')->filter()->max(),
                ];
            }),
            // Zahl der protokollierten REQUEST_CHANGES (Audit-Log), gesetzt vom
            // Board-Endpunkt. Anders als last_review_recommendation überlebt sie ein
            // späteres Approve — Grundlage der Rework-Quote der Performance-Seite.
            'rework_count' => $this->x_rework_count ?? null,
            // Aufenthalte je Status aus dem Protokoll (Status-KEY, Tage, Zahl der
            // Aufenthalte, laufende): Grundlage der Verweildauern auf der
            // Performance-Seite. Styling/Reihenfolge löst der Client auf.
            'status_durations' => $this->x_status_durations ?? null,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_by_name' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->name),
            'claimed_by_id' => $this->claimed_by_id,
            'claimed_by' => $this->whenLoaded('claimer', fn () => $this->claimer?->name),
            'claimed_at' => $this->claimed_at,
            'claim_session' => $this->claim_session_label,
            // Aktive Session: JEDE Skill-Ausfuehrung stempelt sie, auch wenn sie
            // nicht claimt. Ohne sie sieht eine Karte, an der ein fix-Lauf arbeitet,
            // unbearbeitet aus. `..._seen_at` ist das Liveness-Signal (TTL).
            'active_session' => $this->active_session_label,
            'active_session_seen_at' => $this->active_session_seen_at,
            'claim_seen_at' => $this->claim_seen_at,
            'merged_at' => $this->merged_at,
            'last_reviewed_at' => $this->last_reviewed_at,
            'last_review_recommendation' => $this->last_review_recommendation?->value,
            'last_review_summary' => $this->last_review_summary,

            // Computed board fields — present once the task is decorated.
            'gate' => $this->x_gate ?? null,
            'stacking' => $this->x_stacking ?? null,
            'pickable' => $this->when(isset($this->x_pickable), fn () => (bool) $this->x_pickable),
            'unlocks' => $this->x_unlocks ?? null,
            'unmet' => $this->x_unmet ?? null,
            'color' => $this->x_class ?? null,

            'prerequisites' => $this->whenLoaded('prerequisites', fn () => $this->prerequisites->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status?->value ?? $p->orgStatus?->key,
            ])->values()),

            'concern' => $this->whenLoaded('concern', fn () => $this->concern ? [
                'summary' => $this->concern->summary,
                'context' => $this->concern->description_context,
                'blocker' => $this->concern->description_blocker,
                'misconception' => $this->concern->description_misconception,
                'decisions' => $this->concern->description_decisions,
            ] : null),
        ];
    }
}
