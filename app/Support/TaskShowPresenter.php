<?php

namespace App\Support;

use App\Enums\ReviewRecommendation;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Baut die Props für die React-Task-Detailseite (TaskShow.jsx). Sämtliche
 * Freitext-Heuristiken (TaskContentParser) und das Markdown-Rendering laufen
 * serverseitig; React bekommt fertige Strukturen + HTML-Strings und rendert nur.
 * Pendant zur früheren tasks/show.blade.php samt Partials.
 */
class TaskShowPresenter
{
    /** Farbklassen je Status (Spiegel von components/task-status.blade.php). */
    private const STATUS_CLASSES = [
        'UNKNOWN' => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400',
        'BLOCKED' => 'bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300',
        'CONCERNED' => 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300',
        'PICKABLE' => 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-400',
        'CLAIMED' => 'bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300',
        'ANALYZING' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
        'IN_PROGRESS' => 'bg-blue-200 dark:bg-blue-900/50 text-blue-900 dark:text-blue-200',
        'IN_REVIEW' => 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300',
        'COMPLETED' => 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300',
        'MERGED' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300',
    ];

    public function props(Project $project, Task $task): array
    {
        $user = Auth::user();
        $canUpdate = (bool) $user?->can('update', $task);
        $canClaim = (bool) $user?->can('claim', $task);

        $rec = $task->last_review_recommendation;
        $concernOpen = (bool) $task->concern;
        $claimed = (bool) $task->claimed_by_id;
        $hasReview = (bool) ($task->last_reviewed_at || $task->reviewed_by || $task->status === TaskStatus::IN_REVIEW);

        // Titel = Summary ohne abschließenden Klammerzusatz (der wandert in die Untertitel-Zeile).
        $title = (string) $task->summary;
        $subtitle = null;
        if (preg_match('/^(.*\S)\s*\((.+)\)\s*$/u', (string) $task->summary, $m)) {
            $title = $m[1];
            $subtitle = $m[2];
        }

        $descParsed = TaskContentParser::descriptionEvents((string) $task->description);
        $descClean = $descParsed['clean'];
        $descLong = mb_strlen(strip_tags($descClean)) > 320;

        $ta = TaskContentParser::targetActual((string) $task->description_target_actual);

        return [
            'project' => [
                'alias' => $project->alias,
                'showUrl' => route('projects.show', $project),
            ],
            'task' => [
                'name' => $task->name,
                'title' => $title,
                'subtitle' => $subtitle,
                'status' => $this->statusBadge($task->status),
                'recommendation' => $this->recommendation($rec),
                'criticality' => $task->criticality
                    ? ['label' => $task->criticality->label(), 'badgeClasses' => $task->criticality->badgeClasses()]
                    : null,
                'claimed' => $claimed,
                'concernOpen' => $concernOpen,
                'hasReview' => $hasReview,
                'spLabel' => $task->effort_story_points !== null
                    ? __('tasks.points_sp', ['points' => $task->effort_story_points])
                    : null,
            ],
            'header' => [
                'canClaim' => $canClaim,
                'canUpdate' => $canUpdate,
                'claimUrl' => route('projects.tasks.claim', [$project, $task]),
                'editUrl' => route('projects.tasks.edit', [$project, $task]),
                'releaseBlocked' => $claimed && $concernOpen,
            ],
            'metaChips' => $this->metaChips($project, $task),
            'concern' => $concernOpen ? $this->concern($project, $task, $canUpdate) : null,
            'concernCreateUrl' => route('projects.tasks.concern.edit', [$project, $task]),
            'canUpdate' => $canUpdate,
            'description' => [
                'html' => filled($descClean) ? $this->md($descClean) : null,
                'long' => $descLong,
            ],
            'targetActual' => filled($task->description_target_actual)
                ? ($ta
                    ? ['ist' => $ta['ist'] ? $this->md($ta['ist']) : null, 'soll' => $ta['soll'] ? $this->md($ta['soll']) : null]
                    : ['fallback' => $this->md((string) $task->description_target_actual)])
                : null,
            'checklists' => [
                $this->checklist($project, $task, 'acceptance', __('common.acceptance_criteria'), (string) $task->description_acceptance_criteria, '', $canUpdate),
                $this->checklist($project, $task, 'test', __('tasks.test_instructions'), (string) $task->description_test_cases, __('tasks.checked'), $canUpdate),
            ],
            'review' => $hasReview ? $this->review($task, $rec) : null,
            'timeline' => $this->timeline($task, $descParsed['events']),
            'requirements' => $this->requirements($project, $task),
            'claudeLogoPath' => $this->claudeLogoPath(),
            'strings' => $this->strings(),
        ];
    }

    private function statusBadge(TaskStatus|string|null $status): array
    {
        $enum = $status instanceof TaskStatus ? $status : TaskStatus::tryFrom((string) $status);
        $value = $enum?->value ?? (string) $status;

        return [
            'label' => $enum?->label() ?? $value,
            'cls' => self::STATUS_CLASSES[$value] ?? 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300',
        ];
    }

    private function recommendation(?ReviewRecommendation $rec): ?array
    {
        if (! $rec) {
            return null;
        }

        return [
            'label' => $rec->label(),
            'kind' => $rec === ReviewRecommendation::APPROVE
                ? 'approve'
                : ($rec === ReviewRecommendation::REQUEST_CHANGES ? 'changes' : 'other'),
        ];
    }

    private function metaChips(Project $project, Task $task): array
    {
        $repo = $project->githubRepo();
        $chips = [];

        $chips[] = ['label' => __('tasks.creator'), 'value' => $task->creator?->name ?? '—'];

        if ($task->claimer) {
            if ($task->claimed_by_id === $task->created_by_id) {
                $chips[] = ['label' => null, 'value' => __('tasks.self_claimed')];
            } else {
                $chips[] = ['label' => __('tasks.claimed'), 'value' => $task->claimer->name];
            }
        }

        if ($task->phase) {
            $chips[] = ['label' => __('tasks.phase'), 'value' => $task->phase->name];
        }
        if ($task->effort_story_points !== null) {
            $chips[] = ['label' => null, 'value' => __('tasks.points_sp', ['points' => $task->effort_story_points])];
        }
        if ($task->effort_man_days !== null) {
            $chips[] = ['label' => null, 'value' => __('tasks.days_pd', ['days' => (float) $task->effort_man_days])];
        }
        if ($task->effort_tokens !== null) {
            $chips[] = ['label' => null, 'value' => __('tasks.count_tok', ['count' => number_format($task->effort_tokens, 0, ',', '.')])];
        }
        if ($task->affected_files !== null) {
            $chips[] = ['label' => null, 'value' => __('tasks.count_files', ['count' => $task->affected_files])];
        }
        if ($task->pr_number) {
            $chips[] = [
                'label' => __('tasks.pr'),
                'value' => '#'.$task->pr_number,
                'mono' => true,
                'href' => $repo ? "https://github.com/{$repo}/pull/{$task->pr_number}" : null,
            ];
        }

        return $chips;
    }

    private function concern(Project $project, Task $task, bool $canUpdate): array
    {
        $c = $task->concern;

        $details = [];
        foreach ([
            'context' => $c->description_context,
            'blocker' => $c->description_blocker,
            'misconception' => $c->description_misconception,
        ] as $key => $value) {
            if (filled($value)) {
                $details[] = [
                    'key' => $key,
                    'label' => __('tasks.'.$key),
                    'html' => $this->md((string) $value),
                    'wide' => $key === 'blocker',
                ];
            }
        }

        $decisions = $this->parseDecisions((string) $c->description_decisions);

        return [
            'summary' => $c->summary,
            'byName' => __('tasks.concern_by_name', ['name' => $c->creator?->name]),
            'canUpdate' => $canUpdate,
            'editUrl' => route('projects.tasks.concern.edit', [$project, $task]),
            'destroyUrl' => route('projects.tasks.concern.destroy', [$project, $task]),
            'details' => $details,
            'decisions' => $decisions,
            'claudeConfig' => [
                'alias' => $project->alias,
                'taskName' => $task->name,
                'ticketUrl' => route('projects.tasks.show', [$project, $task]),
                'summary' => $c->summary,
                'decisions' => $decisions,
            ],
        ];
    }

    /**
     * Entscheidungen strukturiert einlesen (eine je Zeile, Optionen als CSV ";").
     * Spiegel der Logik in _concern-banner.blade.php.
     *
     * @return array<int, array{question: string, options: array<int, string>}>
     */
    private function parseDecisions(string $raw): array
    {
        $decisions = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = array_map('trim', str_getcsv($line, ';'));
            $question = array_shift($parts);
            if ($question === null || $question === '') {
                continue;
            }
            if (count($parts) && preg_match('/^(.*?)\(a\)\s*(.+)$/su', $question, $m)) {
                $question = trim($m[1], " \t\n\r\0\x0B:-") ?: __('tasks.decision');
                array_unshift($parts, trim($m[2]));
            }
            $options = array_map(
                fn ($o) => preg_replace('/^\([a-z]\)\s*/i', '', $o),
                array_filter($parts, fn ($o) => $o !== ''),
            );
            $decisions[] = ['question' => $question, 'options' => array_values($options)];
        }

        return $decisions;
    }

    private function checklist(Project $project, Task $task, string $kind, string $title, string $source, string $unit, bool $canUpdate): array
    {
        $items = $task->checklistItems->where('kind', $kind)->sortBy('position')->values();
        $hints = $items->where('role', 'hint')->pluck('text')->values()->all();
        $listItems = $items->reject(fn ($i) => $i->role === 'hint')->values();
        $checkable = $items->filter(fn ($i) => $i->isCheckable());
        $done = $checkable->where('checked', true)->count();
        $total = $checkable->count();
        $hasContent = $items->isNotEmpty() || filled($source);

        $base = [
            'kind' => $kind,
            'title' => $title,
            'unit' => $unit,
            'hasContent' => $hasContent,
            'canUpdate' => $canUpdate,
        ];

        if (! $hasContent) {
            return $base + ['mode' => 'empty'];
        }

        if ($items->isNotEmpty()) {
            return $base + [
                'mode' => 'items',
                'done' => $done,
                'total' => $total,
                'items' => $listItems->map(fn ($i) => [
                    'id' => $i->id,
                    'role' => $i->role,
                    'text' => $i->text,
                    'checkable' => $i->isCheckable(),
                    'checked' => (bool) $i->checked,
                    'toggleUrl' => route('projects.tasks.checklist.toggle', [$project, $task, $i->id]),
                ])->all(),
                'hints' => $hints,
            ];
        }

        // Keine Items, aber Alt-Prosa: read-only splitten + Umwandeln-Button.
        $parsed = TaskContentParser::checklist($source, $kind);

        return $base + [
            'mode' => 'prose',
            'parsed' => array_map(fn ($p) => $p['text'], $parsed),
            'proseHtml' => count($parsed) ? null : $this->md($source),
            'convertUrl' => route('projects.tasks.checklist.convert', [$project, $task]),
        ];
    }

    private function review(Task $task, ?ReviewRecommendation $rec): array
    {
        $isApprove = $rec === ReviewRecommendation::APPROVE;
        $isChanges = $rec === ReviewRecommendation::REQUEST_CHANGES;

        $summary = (string) $task->last_review_summary;
        $tldr = null;
        $config = null;
        if ($summary !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $summary) as $line) {
                if ($tldr === null && preg_match('/^\s*\**\s*TLDR\s*:?\s*\**\s*(.+)$/iu', $line, $m)) {
                    $tldr = trim($m[1]);
                } elseif ($config === null && preg_match('/^\s*\**\s*Review-Konfiguration\s*:?\s*\**\s*(.+)$/iu', $line, $m)) {
                    $config = trim($m[1]);
                }
            }
        }

        return [
            'kind' => $isApprove ? 'approve' : ($isChanges ? 'changes' : 'other'),
            'label' => $rec?->label() ?? __('tasks.pending'),
            'hasRec' => $rec !== null,
            'tldr' => $tldr,
            'config' => $config,
            'reviewer' => $task->reviewer?->name ?? '—',
            'lastReviewed' => $task->last_reviewed_at?->format('d.m.Y H:i') ?? '—',
            'summaryHtml' => $summary !== '' ? $this->md($summary) : null,
        ];
    }

    /**
     * @param  array<int, array{label: string, match: string, text: string, date: ?\Carbon\Carbon}>  $events
     */
    private function timeline(Task $task, array $events): array
    {
        $timeline = [];
        $push = function (?\Carbon\Carbon $when, string $title, ?string $body) use (&$timeline) {
            $timeline[] = ['when' => $when, 'title' => $title, 'body' => $body];
        };

        $push($task->created_at, __('tasks.created'), $task->creator?->name);
        if ($task->claimed_at) {
            $push($task->claimed_at, __('tasks.claimed'), $task->claimer?->name);
        }
        if ($task->concern) {
            $push($task->concern->created_at, __('tasks.concern_reported'), $task->concern->creator?->name);
        }
        if ($task->last_reviewed_at) {
            $detail = trim(($task->last_review_recommendation?->label() ?? __('tasks.reviewed_2')).($task->reviewer ? ' · '.$task->reviewer->name : ''));
            $push($task->last_reviewed_at, __('tasks.reviewed'), $detail);
        }
        if ($task->merged_at) {
            $push($task->merged_at, __('tasks.merged'), null);
        }
        foreach ($events as $e) {
            $body = preg_replace('/^\**\s*'.preg_quote($e['match'], '/').'\b\s*:?\s*\**\s*/iu', '', $e['text']);
            $push($e['date'] ?? null, $e['label'], trim((string) $body) ?: null);
        }

        usort($timeline, fn ($a, $b) => ($a['when']?->timestamp ?? PHP_INT_MAX) <=> ($b['when']?->timestamp ?? PHP_INT_MAX));

        return array_map(fn ($e) => [
            'title' => $e['title'],
            'when' => $e['when']?->format('d.m.Y H:i') ?? '',
            'body' => $e['body'],
        ], $timeline);
    }

    private function requirements(Project $project, Task $task): array
    {
        $doneStatuses = [TaskStatus::COMPLETED, TaskStatus::MERGED];

        return [
            'prerequisites' => $task->prerequisites->map(fn ($pre) => [
                'name' => $pre->name,
                'summary' => $pre->summary,
                'url' => route('projects.tasks.show', [$project, $pre]),
                'done' => in_array($pre->status, $doneStatuses, true),
            ])->all(),
            'dependents' => $task->dependents->map(fn ($dep) => [
                'name' => $dep->name,
                'summary' => $dep->summary,
                'url' => route('projects.tasks.show', [$project, $dep]),
            ])->all(),
        ];
    }

    /** Rendert Markdown identisch zu <x-markdown> (nur der innere HTML-Body). */
    private function md(string $content): string
    {
        return Str::markdown($content, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => "<br>\n"],
        ]);
    }

    private function claudeLogoPath(): string
    {
        return 'M4.709 15.955l4.72-2.647.08-.23-.08-.128H9.2l-.79-.048-2.698-.073-2.339-.097-2.266-.122-.571-.121L0 11.784l.055-.352.48-.321.686.06 1.52.103 2.278.158 1.652.097 2.449.255h.389l.055-.157-.134-.098-.103-.097-2.358-1.596-2.552-1.688-1.336-.972-.724-.491-.364-.462-.158-1.008.656-.722.881.06.225.061.893.686 1.908 1.476 2.491 1.833.365.304.145-.103.019-.073-.164-.274-1.355-2.446-1.446-2.49-.644-1.032-.17-.619a2.97 2.97 0 01-.104-.729L6.283.134 6.696 0l.996.134.42.364.62 1.414 1.002 2.229 1.555 3.03.456.898.243.832.091.255h.158V9.01l.128-1.706.237-2.095.23-2.695.08-.76.376-.91.747-.492.583.28.48.685-.067.444-.286 1.851-.559 2.903-.364 1.942h.212l.243-.242.985-1.306 1.652-2.064.73-.82.85-.904.547-.431h1.033l.76 1.129-.34 1.166-1.064 1.347-.881 1.142-1.264 1.7-.79 1.36.073.11.188-.02 2.856-.606 1.543-.28 1.841-.315.833.388.091.395-.328.807-1.969.486-2.309.462-3.439.813-.042.03.049.061 1.549.146.662.036h1.622l3.02.225.79.522.474.638-.079.485-1.215.62-1.64-.389-3.829-.91-1.312-.329h-.182v.11l1.093 1.068 2.006 1.81 2.509 2.33.127.578-.322.455-.34-.049-2.205-1.657-.851-.747-1.926-1.62h-.128v.17l.444.649 2.345 3.521.122 1.08-.17.353-.608.213-.668-.122-1.374-1.925-1.415-2.167-1.143-1.943-.14.08-.674 7.254-.316.37-.729.28-.607-.461-.322-.747.322-1.476.389-1.924.315-1.53.286-1.9.17-.632-.012-.042-.14.018-1.434 1.967-2.18 2.945-1.726 1.845-.414.164-.717-.37.067-.662.401-.589 2.388-3.036 1.44-1.882.93-1.086-.006-.158h-.055L4.132 18.56l-1.13.146-.487-.456.061-.746.231-.243 1.908-1.312-.006.006z';
    }

    private function strings(): array
    {
        return [
            'edit' => __('common.edit'),
            'remove' => __('common.remove'),
            'create' => __('common.create'),
            'claim' => __('common.claim'),
            'release' => __('common.release'),
            'description' => __('common.description'),
            'overview' => __('common.overview'),
            'status' => __('common.status'),
            'blocks' => __('common.blocks'),
            'cannotReleaseWithConcern' => __('tasks.cannot_be_released_while_a_concern_is'),
            'criticality' => __('tasks.criticality'),
            'review' => __('tasks.review'),
            'pending' => __('tasks.pending'),
            'effort' => __('tasks.effort'),
            'showMore' => __('tasks.show_more'),
            'showLess' => __('tasks.show_less'),
            'actualTargetComparison' => __('tasks.actual_target_comparison'),
            'actual' => __('tasks.actual'),
            'before' => __('tasks.before'),
            'target' => __('tasks.target'),
            'after' => __('tasks.after'),
            'scope' => __('tasks.scope'),
            'doneWhen' => __('tasks.done_when'),
            'contract' => __('tasks.contract'),
            'verificationStep' => __('tasks.verification_step'),
            'saved' => __('tasks.saved'),
            'errorNotSaved' => __('tasks.error_not_saved'),
            'note' => __('tasks.note'),
            'convertToChecklist' => __('tasks.convert_to_checklist'),
            'tldr' => __('tasks.tldr'),
            'reviewer' => __('tasks.reviewer'),
            'lastReviewed' => __('tasks.last_reviewed'),
            'hideAnalysis' => __('tasks.hide_analysis'),
            'showDetailedAnalysis' => __('tasks.show_detailed_analysis'),
            'history' => __('tasks.history'),
            'prerequisites' => __('tasks.prerequisites'),
            'none' => __('tasks.none'),
            'noConcern' => __('tasks.no_concern'),
            'removeConcern' => __('tasks.remove_concern'),
            'openDecisions' => __('common.open_decisions'),
            'findDecisionWithClaude' => __('tasks.find_a_decision_with_claude'),
            'decision' => __('tasks.decision'),
            'of' => __('tasks.of'),
            'answered' => __('tasks.answered'),
            'orYourOwnAnswer' => __('tasks.or_your_own_answer'),
            'answer' => __('tasks.answer'),
            'enterYourOwnDecision' => __('tasks.enter_your_own_decision'),
            'back' => __('tasks.back'),
            'next' => __('tasks.next'),
            'toSummary' => __('tasks.to_summary'),
            'decisionsMadeReview' => __('tasks.decisions_made_review_them_and_launch'),
            'notSpecified' => __('tasks.not_specified'),
            'change' => __('tasks.change'),
            'implementWithClaude' => __('tasks.implement_with_claude'),
            // Prompt-Templates für den „Mit Claude umsetzen"-Launch.
            'concernDecisionsIntro' => __('tasks.concern_decisions_intro'),
            'decisionsMade' => __('tasks.decisions_made'),
            'implementTheseDecisions' => __('tasks.implement_these_decisions'),
        ];
    }
}
