<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * FAQ / Nachschlagewerk (ehemals faq/index.blade.php + faq/status-logic.blade.php).
 * Reine Inhaltsseiten: die enum-getriebenen Daten (Status-Badges, Transitions)
 * werden serverseitig aufbereitet und als Props übergeben; die Anzeige baut die
 * React-Seite. Kein asynchrones Nachladen nötig — der Inhalt IST die Nutzlast.
 */
class FaqController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('FaqIndex', [
            'tabs' => $this->tabs('index'),
            'articles' => [
                [
                    'href' => route('faq.status-logic'),
                    'title' => __('faq.status_logic_rules'),
                    'desc' => __('faq.how_a_task_s_status_comes_about_and'),
                ],
                [
                    'href' => route('faq.commands'),
                    'title' => __('faq_commands.title'),
                    'desc' => __('faq_commands.intro'),
                ],
            ],
            'strings' => [
                'faq' => __('faq.faq'),
                'intro' => __('faq.a_reference_for_planstack_s_concepts'),
            ],
        ]);
    }

    /**
     * Kommandos & Status: die Sub-Kommandos des planstack-Skills mit ihrer
     * chronologischen Aufruf-Kette, jeder Status mit seiner konkreten Wirkung und
     * die Fortschritts-Events. Inhaltlich die Kurzform des serverseitig
     * gepflegten Betriebshandbuchs (resources/skill-templates/) — die Aufrufe
     * selbst stehen als Literale hier, nicht in den Sprachdateien: Pfade und
     * `gh`-Kommandos sind Code und laufen nicht durch die Übersetzung.
     */
    public function commands(): InertiaResponse
    {
        $t = fn (string $key) => __('faq_commands.'.$key);

        // Ein Task von Anfang bis Ende — mit dem Status, in dem er danach steht
        // (null = dieser Schritt ändert den Status nicht) und, wo der Auto-Modus
        // sie neu schreibt, dem Inhalt der Sticky-Statuszeile. Die Klammer trägt
        // die laufende Phase (Wähle/Lade Details/Analyse/Bearbeite/… —
        // verbindliche Liste im skill-instructions-Abschnitt „Sticky-Statuszeile"),
        // nicht das gröbere `action`-Feld. Die Zeilen sind Beispiele dessen, was
        // das Skill schreibt, und deshalb — wie die Aufrufe — Literale: das Skill
        // formuliert sie deutsch, unabhängig von der UI-Sprache. Geschrieben wird
        // als ERSTE Handlung des Schritts, nicht danach.
        $lifecycle = [
            ['GET /projects/{p}/board', 'lc_board', null, '⚙ Work (Wähle) DCE · — Board lesen'],
            ['POST /projects/{p}/claim-next', 'lc_claim_next', 'CLAIMED', '⚙ Work (Wähle) DCE · C27 — beansprucht'],
            ['GET /projects/{p}/tasks/{t}?fields=full', 'lc_details', null, '⚙ Work (Lade Details) DCE · C27 — Aufgabe lesen'],
            ['POST …/tasks/{t}/events {ANALYZING}', 'lc_analyze', 'ANALYZING', '⚙ Work (Analyse 60 %) DCE · C27 — 3/5 Teilschritte'],
            ['POST …/tasks/{t}/events {PROCESSING}', 'lc_in_progress', 'IN_PROGRESS', '⚙ Work (Bearbeite 44 %) DCE · C27 — 4/9 Dateien'],
            ['POST …/tasks/{t}/concern', 'lc_concern', 'CONCERNED', '⚙ Work (Concern) DCE · C27 — Concern gemeldet'],
            ['gh pr create', 'lc_pr_create', null, '⚙ Work (Erstelle PR) DCE · C27'],
            ['POST …/tasks/{t}/pr {pr_number}', 'lc_pr_set', null, '⚙ Work (Hinterlege PR) DCE · C27 — PR #418'],
            ['POST …/tasks/{t}/events {POLISHING}', 'lc_polishing', 'BEREINIGEN', '⚙ Fix (Fix 40 %) DCE · C27 — 2/5 Checks'],
            ['POST …/tasks/{t}/events {POLISHED}', 'lc_polished', 'REVIEWBAR', '⚙ Fix (Fix 100 %) DCE · C27 — 5/5 Checks'],
            ['POST …/tasks/{t}/review-claim · events {REVIEWING}', 'lc_review_claim', 'IN_REVIEW', '⚙ Review (Review 25 %) DCE · A1 — 2/8 Dateien im Diff'],
            ['POST …/tasks/{t}/review {recommendation,summary}', 'lc_review_result', null, '⚙ Review (Review 100 %) DCE · A1 — Ergebnis erfassen (Approve/Request Changes)'],
            ['POST …/tasks/{t}/events {APPROVED}', 'lc_approved', 'APPROVED', '⚙ Review (Review) DCE · A1 — freigegeben'],
            ['POST …/tasks/{t}/events {CHANGES_REQUESTED}', 'lc_changes_requested', 'IN_PROGRESS', '⚙ Work (Bearbeite 0 %) DCE · A1 — 0/3 Kommentare'],
            ['POST …/tasks/{t}/merge', 'lc_merge', 'MERGED', '⚙ Work (Fertigstellung) DCE · C27 — mergen'],
            ['— (PR-Abgleich)', 'lc_sync', 'MERGED', null],
            ['POST …/tasks/{t}/events {DEPLOYED}', 'lc_deployed', 'COMPLETED', null],
        ];

        $commands = [
            ['/planstack work <PROJECT>', 'cmd_work_board_purpose', [
                ['POST /projects/{p}/claim-next', 'cmd_work_board_1', '⚙ Work (Wähle) DCE · C27 — beansprucht'],
                ['POST …/tasks/{t}/events {ANALYZING}', 'cmd_work_board_2', '⚙ Work (Analyse 60 %) DCE · C27 — 3/5 Teilschritte'],
                ['POST …/tasks/{t}/events {PROCESSING}', 'cmd_work_board_3', '⚙ Work (Bearbeite 44 %) DCE · C27 — 4/9 Dateien'],
                ['gh pr create · POST …/tasks/{t}/pr', 'cmd_work_board_4', '⚙ Work (Hinterlege PR) DCE · C27 — PR #418'],
                ['POST …/tasks/{t}/events {POLISHED} · /merge', 'cmd_work_board_5', '⚙ Work (Fertigstellung) DCE · C27 — mergen'],
                ['GET /projects/{p}/board', 'cmd_work_board_6', '⚙ Work (Wähle) DCE · — Board lesen'],
            ], '⚙ Work (Bearbeite 44 %) DCE · C27 — 4/9 Dateien'],
            ['/planstack work <PROJECT> <TASK>', 'cmd_work_task_purpose', [
                ['POST /projects/{p}/tasks/{t}/claim', 'cmd_work_task_1', '⚙ Work (Wähle) DCE · C27 — beansprucht'],
                ['GET /projects/{p}/tasks/{t}', 'cmd_work_task_2', '⚙ Work (Lade Details) DCE · C27 — Aufgabe lesen'],
                ['—', 'cmd_work_task_3', '⚙ Work (Bearbeite 44 %) DCE · C27 — 4/9 Dateien'],
            ], '⚙ Work (Analyse 60 %) DCE · C27 — 3/5 Teilschritte'],
            ['/planstack auto <PROJECT>', 'cmd_auto_purpose', [
                ['~/.claude/planstack-status-<session_id>.txt', 'cmd_auto_1', '⏳ Auto (Wähle) DCE · — Arbeit suchen'],
                ['POST /projects/{p}/review-next', 'cmd_auto_2', '⚙ Review (Review 25 %) DCE · A1 — 2/8 Dateien im Diff'],
                ['GET /projects/{p}/tasks', 'cmd_auto_3', '⚙ Work (Bearbeite 44 %) DCE · C27 — 4/9 Dateien'],
                ['POST /projects/{p}/claim-next', 'cmd_auto_4', '⚙ Work (Analyse 60 %) DCE · C27 — 3/5 Teilschritte'],
                ['POST /projects/{p}/next-action', 'cmd_auto_5', null],
                ['—', 'cmd_auto_6', '⏳ Auto (Idle) DCE · — warte 5 min'],
            ], '⚙ Auto › Work (Bearbeite 44 %) DCE · C27 — 4/9 Dateien'],
            ['/planstack review [<PROJECT>] [<TASK>]', 'cmd_review_purpose', [
                ['POST /projects/{p}/tasks/{t}/review-claim', 'cmd_review_1', '⚙ Auto (Review) DCE · A1 — Review übernommen'],
                ['POST /projects/{p}/review-next', 'cmd_review_2', null],
                ['GET /projects', 'cmd_review_3', null],
                ['/review', 'cmd_review_4', '⚙ Review (Review 25 %) DCE · A1 — 2/8 Dateien im Diff (PR #418)'],
                ['POST …/tasks/{t}/review {recommendation,summary}', 'cmd_review_5', '⚙ Review (Review 100 %) DCE · A1 — Ergebnis erfassen'],
                ['gh pr review --approve | --request-changes', 'cmd_review_6', null],
                ['POST …/tasks/{t}/events {event}', 'cmd_review_7', null],
            ], '⚙ Review (Review 25 %) DCE · A1 — 2/8 Dateien im Diff'],
            ['/planstack fix [<PROJECT>] <TASK|PR>', 'cmd_fix_purpose', [
                ['GET /projects/{p}/tasks/{t}', 'cmd_fix_1', '⚙ Fix (Fix) L2L · G5 — PR #418 bestimmen'],
                ['POST …/tasks/{t}/events {POLISHING}', 'cmd_fix_2', null],
                ['git fetch · git merge origin/<base>', 'cmd_fix_3', '⚙ Fix (Fix 0 %) L2L · G5 — 0/5 Checks'],
                ['gh pr view --comments', 'cmd_fix_4', '⚙ Fix (Fix 40 %) L2L · G5 — 3/7 Kommentare'],
                ['gh api …/pulls/{pr}/comments', 'cmd_fix_5', '⚙ Fix (Fix 60 %) L2L · G5 — 1/4 Review-Threads'],
                ['gh pr checks', 'cmd_fix_6', '⚙ Fix (Fix 80 %) L2L · G5 — 4/5 Checks'],
                ['POST …/tasks/{t}/events {POLISHED}', 'cmd_fix_7', '⚙ Fix (Fix 100 %) L2L · G5 — 5/5 Checks'],
            ], '⚙ Fix (Fix 40 %) L2L · G5 — 3/7 Kommentare'],
            ['/planstack plan [<PROJECT>]', 'cmd_plan_purpose', [
                ['GET /projects/{p}/config', 'cmd_plan_1', '⚙ Plan (Lade Config) DCE — plan_instructions'],
                ['POST /projects', 'cmd_plan_2', '⚙ Plan (Plane) — Projekt anlegen'],
                ['POST /projects/{p}/phases', 'cmd_plan_3', '⚙ Plan (Plane 50 %) DCE — 2/4 Phasen'],
                ['POST /projects/{p}/tasks', 'cmd_plan_4', '⚙ Plan (Plane 43 %) DCE — 3/7 Tasks'],
            ], '⚙ Plan (Plane 43 %) DCE — 3/7 Tasks'],
            ['/planstack settings', 'cmd_settings_purpose', [], '⚙ Settings (Einstellungen) — settings.json lesen'],
            ['/planstack update-config [<PROJECT>]', 'cmd_update_config_purpose', [
                ['GET /projects/{p}/config', 'cmd_update_config_1', '⚙ Update (Lade Config) DCE — GET /config'],
                ['GET /skill', 'cmd_update_config_2', '⚙ Update (Schreibe Skill) — SKILL.md ersetzen'],
                ['config.json', 'cmd_update_config_3', '⚙ Update (Baseline) — config.json'],
            ], '⚙ Update (Schreibe Skill) — SKILL.md ersetzen'],
        ];

        // Status in Lebenszyklus-Reihenfolge. Zu den Kern-Status aus dem Enum
        // kommen die Spalten der Standard-Konfiguration, die keinen eigenen
        // TaskStatus-Fall haben (BEREINIGEN/REVIEWBAR/APPROVED) — sie sind hier
        // bewusst FEST hinterlegt und nicht aus der Org-Konfiguration gelesen: der
        // Artikel beschreibt den Standard, nicht die Einstellungen des Lesers.
        // Ihr Badge nutzt die Klassen des nächstliegenden Kern-Status, damit die
        // Seite bei ihrer eigenen Palette bleibt (keine Org-Farbtokens).
        $extra = [
            'BEREINIGEN' => ['active', TaskStatus::IN_PROGRESS, false],
            'REVIEWBAR' => ['review', TaskStatus::IN_REVIEW, false],
            'APPROVED' => ['done', TaskStatus::COMPLETED, true],
        ];

        $order = [
            TaskStatus::UNKNOWN, TaskStatus::BLOCKED, TaskStatus::CONCERNED,
            TaskStatus::PICKABLE, TaskStatus::CLAIMED, TaskStatus::ANALYZING,
            TaskStatus::IN_PROGRESS, 'BEREINIGEN', 'REVIEWBAR', TaskStatus::IN_REVIEW,
            'APPROVED', TaskStatus::COMPLETED, TaskStatus::MERGED,
        ];

        $statuses = collect($order)->map(function ($s) use ($t, $extra) {
            $isEnum = $s instanceof TaskStatus;
            $name = $isEnum ? $s->name : $s;
            $low = strtolower($name);

            return [
                'name' => $name,
                'value' => $name,
                'kind' => $t('kind_'.($isEnum ? $s->kind() : $extra[$name][0])),
                // Kern-Status erben ihre Bedeutung von der Statuslogik-Seite; die
                // Standard-Spalten bringen eine eigene mit.
                'meaning' => $isEnum ? __('faq.status_'.$low.'_desc') : $t('status_'.$low.'_meaning'),
                'does' => $t('status_'.$low.'_does'),
                'setBy' => $t('status_'.$low.'_set_by'),
                'next' => $t('status_'.$low.'_next'),
                'stored' => $isEnum
                    ? ($s->isExplicit() || $s === TaskStatus::UNKNOWN || $s === TaskStatus::PICKABLE)
                    : true,
                'derived' => $isEnum && ! $s->isExplicit(),
                'countsDone' => $isEnum ? $s->isDone() : $extra[$name][2],
                // Kennzeichnet die Spalten ohne eigenen TaskStatus-Fall.
                'orgColumn' => ! $isEnum,
            ];
        })->values()->all();

        // Fortschritts-Events in Ablauf-Reihenfolge. Drittes Feld = Zielstatus der
        // Standard-Konfiguration (null ⇒ das Event ist reines Protokoll und setzt
        // keinen Status). Auch hier fest hinterlegt, nicht aus der Org gelesen.
        $events = [
            ['CLAIMING', 'ev_claiming', null],
            ['CLAIMED', 'ev_claimed', 'CLAIMED'],
            ['ANALYZING', 'ev_analyzing', 'ANALYZING'],
            ['ANALYZED', 'ev_analyzed', null],
            ['PROCESSING', 'ev_processing', 'IN_PROGRESS'],
            ['PROCESSED', 'ev_processed', null],
            ['PUBLISHING', 'ev_publishing', null],
            ['POLISHING', 'ev_polishing', 'BEREINIGEN'],
            ['POLISHED', 'ev_polished', 'REVIEWBAR'],
            ['REVIEWING', 'ev_reviewing', 'IN_REVIEW'],
            ['REVIEWED', 'ev_reviewed', null],
            ['APPROVED', 'ev_approved', 'APPROVED'],
            ['CHANGES_REQUESTED', 'ev_changes_requested', 'IN_PROGRESS'],
            ['CONCERNED', 'ev_concerned', 'CONCERNED'],
            ['UNCLAIMED', 'ev_unclaimed', 'PICKABLE'],
            ['MERGED', 'ev_merged', 'MERGED'],
            ['DEPLOYED', 'ev_deployed', 'COMPLETED'],
        ];

        // Erlaubte Statuswechsel der Standard-Konfiguration. Board-Drag UND
        // API/MCP-Aktionen werden dagegen geprüft; ein unerlaubter Wechsel → 409.
        $transitions = [
            ['PICKABLE', ['CLAIMED', 'BLOCKED', 'CONCERNED']],
            ['CLAIMED', ['PICKABLE', 'ANALYZING', 'IN_PROGRESS', 'IN_REVIEW', 'BLOCKED', 'CONCERNED']],
            ['ANALYZING', ['PICKABLE', 'CLAIMED', 'IN_PROGRESS', 'IN_REVIEW', 'BLOCKED', 'CONCERNED']],
            ['IN_PROGRESS', ['PICKABLE', 'ANALYZING', 'BEREINIGEN', 'REVIEWBAR', 'IN_REVIEW', 'COMPLETED', 'BLOCKED', 'CONCERNED']],
            ['BEREINIGEN', ['ANALYZING', 'IN_PROGRESS', 'REVIEWBAR', 'IN_REVIEW']],
            ['REVIEWBAR', ['PICKABLE', 'IN_PROGRESS', 'BEREINIGEN', 'IN_REVIEW', 'BLOCKED', 'CONCERNED']],
            ['IN_REVIEW', ['PICKABLE', 'IN_PROGRESS', 'REVIEWBAR', 'APPROVED', 'MERGED', 'COMPLETED', 'BLOCKED', 'CONCERNED']],
            ['APPROVED', ['PICKABLE', 'IN_PROGRESS', 'IN_REVIEW', 'MERGED', 'BLOCKED', 'CONCERNED']],
            ['MERGED', ['PICKABLE', 'COMPLETED', 'BLOCKED', 'CONCERNED']],
            ['COMPLETED', ['PICKABLE', 'IN_REVIEW', 'MERGED', 'BLOCKED', 'CONCERNED']],
            ['BLOCKED', ['PICKABLE', 'CLAIMED', 'ANALYZING', 'IN_PROGRESS', 'REVIEWBAR', 'IN_REVIEW', 'APPROVED', 'MERGED', 'COMPLETED', 'CONCERNED']],
            ['CONCERNED', ['PICKABLE', 'CLAIMED', 'ANALYZING', 'IN_PROGRESS', 'REVIEWBAR', 'IN_REVIEW', 'APPROVED', 'MERGED', 'COMPLETED', 'BLOCKED']],
        ];

        // Felder, die der Server beim Eintritt in einen Status selbst füllt —
        // jeweils nur, wenn das Feld noch leer ist.
        $fieldAutomations = [
            ['CLAIMED', 'claimed_by_id = @actor · claimed_at = @now'],
            ['IN_REVIEW', 'reviewed_by = @actor'],
            ['MERGED', 'merged_at = @now'],
        ];

        $stringKeys = [
            'faq', 'title', 'intro',
            'lifecycle_title', 'lifecycle_hint', 'lifecycle_no_change',
            'commands_title', 'commands_hint', 'no_calls',
            'th_step', 'th_call', 'th_what', 'th_status',
            'status_line_label', 'status_line_note',
            'statuses_title', 'statuses_hint', 'statuses_note',
            'th_status_single', 'th_meaning', 'th_does', 'th_set_by', 'th_next',
            'flag_derived', 'flag_stored', 'flag_counts_done',
            'events_title', 'events_hint', 'events_best_effort', 'events_authoritative',
            'events_merged_note', 'events_default_note',
            'th_event', 'th_when', 'th_effect', 'events_target_none',
            'transitions_title', 'transitions_hint', 'transitions_note', 'th_from', 'th_to',
            'fields_title', 'fields_hint', 'fields_note', 'th_on_status', 'th_fields',
        ];

        return Inertia::render('FaqCommands', [
            'tabs' => $this->tabs('commands'),
            'badges' => collect($extra)->reduce(
                fn (array $carry, array $def, string $key) => $carry + [$key => [
                    'label' => $t('label_'.strtolower($key)),
                    'classes' => $def[1]->badgeClasses(),
                    'value' => $key,
                ]],
                $this->badges(),
            ),
            'lifecycle' => collect($lifecycle)->map(fn ($r) => [
                'call' => $r[0], 'what' => $t($r[1]), 'status' => $r[2], 'statusLine' => $r[3],
            ])->all(),
            'commands' => collect($commands)->map(fn ($c) => [
                'name' => $c[0],
                'purpose' => $t($c[1]),
                // Beispielzeile des Kommandos selbst — auch `settings` zeigt eine,
                // obwohl es keine API-Aufrufe hat.
                'statusLine' => $c[3] ?? null,
                'steps' => collect($c[2])->map(fn ($s) => [
                    'call' => $s[0], 'what' => $t($s[1]), 'statusLine' => $s[2] ?? null,
                ])->all(),
            ])->all(),
            'statuses' => $statuses,
            'events' => collect($events)->map(fn ($e) => [
                'event' => $e[0], 'when' => $t($e[1]), 'target' => $e[2],
            ])->all(),
            'transitions' => collect($transitions)->map(fn ($r) => [
                'from' => $r[0], 'to' => $r[1],
            ])->all(),
            'fieldAutomations' => collect($fieldAutomations)->map(fn ($r) => [
                'status' => $r[0], 'fields' => $r[1],
            ])->all(),
            'strings' => collect($stringKeys)
                ->mapWithKeys(fn (string $k) => [
                    lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $k)))) => $k === 'faq' ? __('faq.faq') : $t($k),
                ])->all(),
        ]);
    }

    public function statusLogic(): InertiaResponse
    {
        $badges = $this->badges();

        $meta = [
            'UNKNOWN' => ['faq.status_unknown_desc', ['faq.stored', 'faq.derived']],
            'BLOCKED' => ['faq.status_blocked_desc', ['faq.derived']],
            'CONCERNED' => ['faq.status_concerned_desc', ['faq.stored']],
            'PICKABLE' => ['faq.status_pickable_desc', ['faq.stored', 'faq.derived']],
            'CLAIMED' => ['faq.status_claimed_desc', ['faq.stored']],
            'ANALYZING' => ['faq.status_analyzing_desc', ['faq.stored']],
            'IN_PROGRESS' => ['faq.status_in_progress_desc', ['faq.stored']],
            'IN_REVIEW' => ['faq.status_in_review_desc', ['faq.stored']],
            'COMPLETED' => ['faq.status_completed_desc', ['faq.stored']],
            'MERGED' => ['faq.status_merged_desc', ['faq.stored']],
        ];

        // Legende in Anzeige-Reihenfolge (umgekehrt wie die Blade-Vorlage).
        $legend = collect(array_reverse(TaskStatus::displayOrder()))
            ->map(function (TaskStatus $s) use ($meta) {
                [$desc, $kinds] = $meta[$s->name];

                return [
                    'name' => $s->name,
                    'value' => $s->value,
                    'desc' => __($desc),
                    'kinds' => collect($kinds)->map(fn ($k) => [
                        'label' => __($k),
                        'derived' => $k === 'faq.derived',
                    ])->all(),
                ];
            })->values()->all();

        $transitionDefs = [
            ['faq.trigger_create_task', ['UNKNOWN'], 'faq.note_new_task_no_status'],
            ['faq.trigger_claim', ['CLAIMED'], 'faq.note_sets_assignee_timestamp'],
            ['faq.trigger_release', ['PICKABLE'], 'faq.note_assignee_removed'],
            ['faq.trigger_status_analyze', ['ANALYZING'], null],
            ['faq.trigger_status_in_progress', ['IN_PROGRESS'], null],
            ['faq.trigger_status_in_review', ['IN_REVIEW'], null],
            ['faq.trigger_status_done', ['IN_REVIEW', 'IN_PROGRESS'], 'faq.note_in_review_if_pr_else_in_progress'],
            ['faq.trigger_report_problem', ['CONCERNED'], 'faq.note_unless_already_merged'],
            ['faq.trigger_resolve_problem', ['CLAIMED', 'PICKABLE'], 'faq.note_claimed_or_pickable_on_resolve'],
            ['faq.trigger_merge_manual', ['MERGED'], 'faq.note_stamps_merge_timestamp'],
            ['faq.trigger_pr_sync_detects_merge', ['MERGED'], 'faq.note_github_reports_merged'],
            ['faq.trigger_split_task', ['COMPLETED', 'UNKNOWN'], 'faq.note_split_parent_done_children_pending'],
            ['faq.trigger_manual_edit', [], 'faq.note_any_status_settable'],
            ['faq.trigger_claim_review', [], 'faq.note_no_status_change_sets_reviewer'],
        ];

        $transitions = collect($transitionDefs)->map(fn ($t) => [
            'trigger' => __($t[0]),
            'results' => $t[1],
            'note' => $t[2] ? __($t[2]) : null,
        ])->all();

        return Inertia::render('FaqStatusLogic', [
            'tabs' => $this->tabs('status-logic'),
            'badges' => $badges,
            'legend' => $legend,
            'transitions' => $transitions,
            'strings' => $this->statusLogicStrings(),
        ]);
    }

    /**
     * @return array<int, array{key: string, label: string, href: string, active: bool}>
     */
    private function tabs(string $active): array
    {
        return [
            ['key' => 'index', 'label' => __('common.overview'), 'href' => route('faq.index'), 'active' => $active === 'index'],
            ['key' => 'status-logic', 'label' => __('components.status_logic'), 'href' => route('faq.status-logic'), 'active' => $active === 'status-logic'],
            ['key' => 'commands', 'label' => __('faq_commands.title'), 'href' => route('faq.commands'), 'active' => $active === 'commands'],
        ];
    }

    /**
     * Status-Badge-Katalog (Name → Label + Tailwind-Klassen), damit die
     * React-Seiten Badges inline in den Fließtext setzen können. Geteilt von der
     * Statuslogik- und der Kommando-Seite.
     *
     * @return array<string, array{label: string, classes: string, value: string}>
     */
    private function badges(): array
    {
        return collect(TaskStatus::cases())
            ->mapWithKeys(fn (TaskStatus $s) => [$s->name => [
                'label' => $s->label(),
                'classes' => $s->badgeClasses(),
                'value' => $s->value,
            ]])->all();
    }

    /**
     * Alle Fließtext-Strings der Statuslogik-Seite (Reihenfolge = Vorlage).
     *
     * @return array<string, string>
     */
    private function statusLogicStrings(): array
    {
        $keys = [
            'faq' => 'faq.faq', 'anyNoChange' => 'faq.any_no_change',
            'introPre' => 'faq.how_a_task_s_status_comes_about_and_2',
            'introEmph' => 'faq.no_formal_state_machine',
            'introPost' => 'faq.every_status_is_set_by_a_concrete',
            'legendTitle' => 'faq.the_statuses_at_a_glance',
            'legendHint' => 'faq.ten_states_from_an_open_start_to_the',
            'status' => 'common.status', 'value' => 'faq.value', 'meaning' => 'common.meaning', 'origin' => 'faq.origin',
            'setTitle' => 'faq.how_a_status_is_set',
            'setHint' => 'faq.each_row_is_an_action_and_the_status_it',
            'trigger' => 'faq.trigger', 'result' => 'faq.result', 'note' => 'faq.note',
            'rulesTitle' => 'faq.rules_for_claude_while_working',
            'rulesHintPre' => 'faq.this_is_how_claude_via_the_api_or_the',
            'rulesHintEmph' => 'faq.the_api_is_the_single_source_of_truth',
            'rulesHintPost' => 'faq.there_are_no_local_status_files',
            'chainRead' => 'faq.read_the_board', 'chainChoose' => 'faq.choose_the_best_pick_2', 'chainOr' => 'faq.or',
            'ruleReread' => 'faq.re_read_the_board_before_every_action', 'ruleRereadText' => 'faq.the_state_changes_as_other_people_work',
            'ruleChoose' => 'faq.choose_the_best_pick', 'ruleChooseText' => 'faq.the_pickable_task_with_the_most_unlocks',
            'ruleClaim' => 'faq.claim', 'ruleClaimIsAtomic' => 'faq.is_atomic', 'ruleClaimIfApi' => 'faq.if_the_api_responds_with', 'ruleClaimTaken' => 'faq.the_task_is_already_taken_don_t', 'textWord' => 'common.text',
            'ruleAnalyzeFirst' => 'faq.first_analyze', 'ruleThenImplement' => 'faq.then_implement', 'ruleAnalyzeText' => 'faq.read_the_details_description_acceptance',
            'ruleConcern' => 'faq.not_doable_report_a_concern', 'ruleConcernText' => 'faq.text', 'ruleConcernInstead' => 'faq.instead_of_guessing_for_a_blocker_a', 'orWord' => 'common.or',
            'rulePr' => 'faq.adding_a_pr_does_not_change_the_status', 'rulePrText' => 'faq.but_it_immediately_unlocks_dependent', 'openWord' => 'faq.open', 'rulePrText2' => 'faq.pr_satisfies_their_gate_re_read_the',
            'ruleDone' => 'faq.report_done', 'ruleDoneText2' => 'faq.text_2', 'ruleDoneProvided' => 'faq.provided_a_pr_is_set_without_a_pr_it', 'ruleDoneAlone' => 'faq.a_pr_alone_does_not_make_the_task_done',
            'ruleMerge' => 'faq.only_the_merge_takes_the_task_off_the', 'ruleMergeText' => 'faq.the_merge_call_is_idempotent_the_merge', 'ruleMergeArises' => 'faq.arises_only_via_a_split_parent_task',
            'derivedTitle' => 'faq.derived_display_status',
            'derivedHintPre' => 'faq.an_explicit_work_status', 'derivedHintMid' => 'faq.is_shown_unchanged_only_for', 'derivedHintPost' => 'faq.is_derived_for_display',
            'derived1' => 'faq.is_the_stored_status_an_explicit_work',
            'derived2Pre' => 'faq.otherwise_is_at_least_one_prerequisite', 'derived2Emph' => 'faq.not_delivered', 'derived2Post' => 'faq.text_3',
            'derived3Pre' => 'faq.otherwise_is_the_task', 'derived3Emph' => 'faq.ready_to_start', 'derived3Post' => 'faq.see_pickable_below',
            'derived4' => 'faq.otherwise_it_stays_at',
            'deliveredTitle' => 'faq.delivered_pickable',
            'deliveredEmph' => 'faq.delivered', 'deliveredAsSoon' => 'faq.a_task_is_as_soon_as_it_has_a_pr', 'deliveredOr' => 'faq.or', 'deliveredDone' => 'faq.is_done_merged',
            'deliveredImportant' => 'faq.important_even_a_single', 'deliveredPrCounts' => 'faq.pr_counts_as_delivered_it_satisfies_the',
            'taskIs' => 'faq.a_task_is', 'pickable' => 'common.pickable', 'ifWord' => 'faq.if', 'allWord' => 'faq.all', 'applyWord' => 'faq.apply',
            'pickNotClaimed' => 'faq.not_claimed_no_assignee', 'pickNoPr' => 'faq.no_pr_set', 'pickStatusNone' => 'faq.the_status_is_none_of_merged_done', 'pickAllPrereq' => 'faq.all_prerequisites_are_delivered',
            'blocked' => 'common.blocked', 'vs' => 'faq.vs', 'blockedText' => 'faq.at_least_one_open_prerequisite_blocked',
            'bottleneckTitle' => 'faq.bottleneck_in_the_diagram', 'bottleneckWhen' => 'faq.a_node_gets_the_bottleneck_marker_when',
            'bnTaskIs' => 'faq.the_task_is', 'bnNot' => 'faq.not', 'bnDone' => 'faq.done_merged',
            'bnNumberOf' => 'faq.the_number_of', 'bnInTotal' => 'faq.in_total', 'bnDependent' => 'faq.dependent_downstream_tasks_is_above_the',
            'bnAtLeast2' => 'faq.and_is_at_least_2', 'bnPrSeq' => 'faq.in_addition_the_pr_sequence_counts_each',
            'ciTitle' => 'faq.ci_pr_synchronization', 'ciIntro' => 'faq.the_sync_the_sync_prs_button_or_the',
            'ciEach' => 'faq.for_each_pr_the_state_is_fetched_once',
            'ciAs' => 'faq.as', 'merged' => 'faq.merged', 'ciOnlyIf' => 'faq.a_pr_only_counts_if_github', 'ciOrA' => 'faq.or_a', 'ciTimestamp' => 'faq.timestamp_closed_but_unmerged_prs_are',
            'ciOnMerge' => 'faq.on_merge_the_status_is_set_to', 'ciAndTs' => 'faq.and_the_merge_timestamp_is_set',
            'ciMetrics' => 'faq.pr_metrics_changed_files_commits', 'ciNot' => 'faq.not', 'ciNever' => 'faq.in_review_never_results_from_the_sync',
            'faqTitle' => 'faq.faq',
        ];

        $out = [];
        foreach ($keys as $alias => $key) {
            $out[is_int($alias) ? $key : $alias] = __($key);
        }

        return $out;
    }
}
