<?php

// FAQ article "Commands & statuses" — the planstack skill's sub-commands and the
// board statuses. The calls themselves (paths, gh/git commands) are literals in
// the controller: code does not go through translation.
return [
    'title' => 'Commands & statuses',
    'intro' => 'What the planstack skill\'s sub-commands do, which calls they make in which order, and what each status means exactly. The API is always authoritative — there are no local state files.',

    // Section 1: lifecycle
    'lifecycle_title' => 'A task from start to finish',
    'lifecycle_hint' => 'The calls in the order they normally happen — with the status the task is in afterwards. A step without a status does not change it.',
    'lifecycle_no_change' => 'no change',
    'lc_board' => 'Read the board — pickable tasks, sorted by `unlocks`. Only needed on the manual path; `claim-next` does selection and claim in one call.',
    'lc_claim_next' => 'Pick the best pickable task (most `unlocks`) and claim it atomically. A response of `{"claimed":null}` means nothing (more) is pickable.',
    'lc_details' => 'Fetch the details — description, acceptance criteria, prerequisites. Only needed when the claim did not already return them (`claim.return_details`).',
    'lc_analyze' => 'Analysis begins. Either a direct status change or a progress event — never both: if the organization wires events to statuses, the direct change is dropped.',
    'lc_in_progress' => 'Implementation begins. Same rule: direct change or event, depending on the organization\'s configuration.',
    'lc_concern' => 'A branch instead of carrying on: if the task cannot be done, a concern is reported rather than guessed at. The round ends here.',
    'lc_pr_create' => 'Open the PR. The title convention is binding: `<PROJECT>-<TASK>: <short description>`.',
    'lc_pr_set' => 'Record the PR on the task. The status stays, but the gates of dependent tasks open immediately — an open PR counts as delivered.',
    'lc_done' => 'Report done. With a PR set the task moves to "in review"; without one it stays "in progress".',
    'lc_review_claim' => 'Take over the review. Only sets `reviewed_by`; the response carries `pr_number` and `pr_url`. Your own tasks are not reviewable.',
    'lc_review_result' => 'Record the review result — `APPROVE` or `REQUEST_CHANGES` plus the detailed analysis.',
    'lc_merge' => 'Merge. Idempotent — a second call does no harm. This takes the task off the board.',
    'lc_sync' => 'Alternatively without the skill: the PR sync ("sync PRs" or the CI) detects the merge on GitHub and sets the status server-side.',

    // Section 2: commands
    'commands_title' => 'The commands',
    'commands_hint' => 'One installed skill serves every project the token can access. The sub-command always comes first, followed by the project.',
    'no_calls' => 'No API calls — purely local.',
    'th_step' => 'Step',
    'th_call' => 'Call',
    'th_what' => 'What happens',
    'th_status' => 'Status after',

    'cmd_work_board_purpose' => 'Works the whole board: picks the best pickable task itself, takes it through to the merge and repeats until nothing is pickable any more.',
    'cmd_work_board_1' => 'Pick the best pickable task and claim it — both in one call. `{"claimed":null}` ends the run.',
    'cmd_work_board_2' => 'Analysis: read description, acceptance criteria and prerequisites before any code is written.',
    'cmd_work_board_3' => 'Implement — or, if it genuinely cannot be done, report a concern instead of guessing.',
    'cmd_work_board_4' => 'Open the PR and record it on the task. That immediately opens the gates of dependent tasks.',
    'cmd_work_board_5' => 'Report done and merge.',
    'cmd_work_board_6' => 'Re-read the board (per `reread.policy`) and continue with the next task — the state changes while others work.',

    'cmd_work_task_purpose' => 'Works exactly one task, addressed by name — no auto-pick. The cycle continues from the current status instead of starting over.',
    'cmd_work_task_1' => 'Claim directly by task name — task paths take a name or an id, no name→id lookup needed. A `409` means it is already taken; do not force it.',
    'cmd_work_task_2' => 'Fetch the details if the claim did not return them.',
    'cmd_work_task_3' => 'The same cycle as for the whole board — for this one task only. If it is not pickable (open gate, already claimed, PR already set), that is reported rather than forced.',

    'cmd_auto_purpose' => 'Works the board continuously and unattended. The main agent is only a supervisor and starts auto runs in an endless loop, each as its own subagent handling exactly one unit of work. The mode only ends when it is cancelled.',
    'cmd_auto_1' => 'Set the sticky status line before the unit of work starts — as early as possible, so what is running stays visible.',
    'cmd_auto_2' => 'Priority 1: if something is ready for review, review it.',
    'cmd_auto_3' => 'Priority 2: finish your own open tasks through to a polished PR — via `fix` when an open PR only needs polishing.',
    'cmd_auto_4' => 'Priority 3: implement the best pickable task until the PR is up.',
    'cmd_auto_5' => 'Instead of the three checks separately: decides fix → review → work in one call and reserves the task atomically.',
    'cmd_auto_6' => 'Nothing to do (`idle`): wait 5 minutes, set the status line to "idle", then start over. If the auto run did something, the next one starts immediately.',

    'cmd_review_purpose' => 'Reviews tasks that are ready for review (with a PR). Without a task one is picked automatically within the project; without a project the search spans all projects. Your own tasks are not reviewable.',
    'cmd_review_1' => 'Take over the review of this specific task — sets `reviewed_by`.',
    'cmd_review_2' => 'Or fetch the next ready task automatically. A review you already took over and have not finished comes first and is continued. `{"reviewing":null}` means nothing is pending.',
    'cmd_review_3' => 'Without a project: walk all accessible projects until one returns a task.',
    'cmd_review_4' => 'Run the review skill on the PR — strictness and thoroughness come from the local settings.',
    'cmd_review_5' => 'Record the result: recommendation plus the detailed analysis. By default the review is shown first and the recommendation confirmed — never ask for the decision without having shown the review.',
    'cmd_review_6' => 'Only if the settings say so: also post the result on the PR.',
    'cmd_review_7' => 'Report progress events — `REVIEWED` after recording, then `APPROVED` or `CHANGES_REQUESTED` depending on the recommendation.',

    'cmd_fix_purpose' => 'Brings an open PR back into a mergeable state. Runs entirely through `gh`/`git` on the PR — nothing happens server-side except the progress events. A task or PR number is mandatory.',
    'cmd_fix_1' => 'Resolve the target: a numeric argument is the PR number. A task name yields the PR number from the task. Without a project the search spans all projects.',
    'cmd_fix_2' => 'Polishing begins.',
    'cmd_fix_3' => 'Resolve merge conflicts with the target branch: check out the head branch, merge the base branch in, resolve, commit, push.',
    'cmd_fix_4' => 'Answer PR/issue comments on the merits and fix the code where needed.',
    'cmd_fix_5' => 'Answer review comments on code lines, fix them and resolve the threads.',
    'cmd_fix_6' => 'Reproduce red checks locally, fix them, push — until the CI is green.',
    'cmd_fix_7' => 'Polishing done: CI green, comments answered and resolved. This makes the task reviewable again.',

    'cmd_plan_purpose' => 'Creates projects, phases and tasks. Its instructions are versioned independently and fetched fresh on every call — before planning, not after.',
    'cmd_plan_1' => 'Mandatory first: read `plan_instructions`. They describe the flow, the endpoints and the field-by-field guide for tasks. If no project exists yet, any accessible one will do, just to pull the instructions.',
    'cmd_plan_2' => 'Create a new project (optional).',
    'cmd_plan_3' => 'Create phases.',
    'cmd_plan_4' => 'Create tasks — always pass `affected_files` (the estimated number of files). A binding convention, but not enforced server-side.',

    'cmd_settings_purpose' => 'Shows and changes the skill\'s local settings — tests, PHPStan, PHPCS, babysit PRs, review behaviour and output verbosity. These values are local only and never reach the server.',

    'cmd_update_config_purpose' => 'Pulls the latest server-maintained configuration and the current skill text. Rarely needed by hand: every board response carries the revision headers, and on drift the skill re-adopts on its own.',
    'cmd_update_config_1' => 'Read the configuration — settings, operating manual, status rules, skill instructions and the individual versions (`config_versions`).',
    'cmd_update_config_2' => 'Fetch the served skill text and overwrite your own SKILL.md with it. Mandatory — raising only the revision means following the old text forever, because the drift check never fires again.',
    'cmd_update_config_3' => 'Only then write the returned `skill_revision` as the baseline into `config.json`.',

    'status_line_label' => 'Status line',
    'status_line_note' => 'The sticky status line applies to EVERY invocation of the skill — not just the auto mode, and explicitly including the skill updating itself. It is written BEFORE the next action starts (tool call, HTTP call, file, subagent, phase change): line first, then act — never after, never batched up at the end. The first word is the running command (`Work`, `Auto`, `Review`, `Fix`, `Plan`, `Settings`, `Update`); when one calls another, both appear (`Auto › Work`). Then comes the running phase with the computed progress within it — counter over a real denominator, no denominator no number. It lives as a single line in `~/.claude/planstack-status-<session_id>.txt`; task and PR number are clickable. For it to update at all while a subagent is working, the `statusLine` entry needs a `refreshInterval` (5-10 s).',

    // Section 3: statuses
    'statuses_title' => 'What each status does exactly',
    'statuses_hint' => 'There is no formal state machine: every status is set by a concrete call, or derived from the gate for display.',
    'statuses_note' => 'The table shows the default configuration. "Unknown" is not a configured status but the state of having none. "Polishing", "reviewable" and "approved" are columns of the default configuration without their own core status in the code — the server recognizes them by their role. An organization can rename them and add further columns; the meaning then hangs off the role, not the name.',
    'th_status_single' => 'Status',
    'th_meaning' => 'Meaning',
    'th_does' => 'What it does',
    'th_set_by' => 'Set by',
    'th_next' => 'Next',
    'kind_waiting' => 'waiting',
    'kind_active' => 'in progress',
    'kind_review' => 'review',
    'kind_done' => 'done',
    'kind_exception' => 'exception',
    'flag_derived' => 'derived',
    'flag_stored' => 'stored',
    'flag_counts_done' => 'counts as done',
    'flag_org_column' => 'org column',

    'status_unknown_does' => 'No status stored. For display, the gate decides whether the task shows as blocked or ready to start. Does not count as done and satisfies no gate — dependent tasks stay blocked.',
    'status_unknown_set_by' => 'The state of a newly created task. Nothing is set.',
    'status_unknown_next' => '"Blocked" or "ready to start" for display; "claimed" on a claim.',

    'status_blocked_does' => 'Purely derived, never stored: at least one prerequisite is not delivered (has no PR and is not done). The task is not pickable and `claim-next` skips it.',
    'status_blocked_set_by' => 'Nobody — it is the derivation from the gate. A task cannot be set to "blocked".',
    'status_blocked_next' => '"Ready to start", as soon as every prerequisite has a PR or is done.',

    'status_concerned_does' => 'Takes the task off the regular flow: not pickable, no further work until somebody decides. The reported text is part of the task and visible on the board.',
    'status_concerned_set_by' => 'Reporting a concern, or the progress event `CONCERNED`. No longer applies once the task is merged.',
    'status_concerned_next' => 'On resolution "claimed" (if still claimed), otherwise "ready to start".',

    'status_pickable_does' => 'The task is ready to start: all prerequisites delivered, not claimed, no PR, no done status. `claim-next` picks the one with the most `unlocks` from exactly these.',
    'status_pickable_set_by' => 'The derivation from the gate — or releasing a claimed task, which removes the assignee.',
    'status_pickable_next' => '"Claimed" on a claim.',

    'status_claimed_does' => 'The task belongs to an assignee (plus a timestamp) and is therefore locked for others — a second claim answers `409`. It is no longer pickable, but not being worked on yet.',
    'status_claimed_set_by' => 'The claim (targeted or via `claim-next`), or the progress event `CLAIMED`.',
    'status_claimed_next' => '"Analyzing" — or "concern", if the task turns out not to be doable.',

    'status_analyzing_does' => 'Signals that description, acceptance criteria and prerequisites are being read. No code yet. The task stays claimed and locked.',
    'status_analyzing_set_by' => 'The status change `analyze` or the progress event `ANALYZING` — one of the two depending on the organization, never both.',
    'status_analyzing_next' => '"In progress" — or "concern".',

    'status_in_progress_does' => 'Implementation is underway. The task stays locked; no PR is recorded yet, so the gates of dependent tasks are still closed.',
    'status_in_progress_set_by' => 'The status change `in_progress` or the event `PROCESSING`. Also the result of "done" as long as no PR is set.',
    'status_in_progress_next' => '"In review", once a PR is recorded and "done" is reported.',

    'status_in_review_does' => 'The work is handed in and a PR exists. The task is reviewable — by others, not by the implementer; `review-next` returns exactly these tasks.',
    'status_in_review_set_by' => 'Reporting "done" with a PR set. The PR sync never produces this status.',
    'status_in_review_next' => '"Merged" after the merge.',

    'status_completed_does' => 'Counts as done: feeds into the progress and satisfies the gate of dependent tasks for good. Only ever arises on the parent task of a split.',
    'status_completed_set_by' => 'Splitting a task — the parent counts as done, the children start out open.',
    'status_completed_next' => 'Final state.',

    // Columns of the default configuration without their own TaskStatus case.
    'label_bereinigen' => 'polishing',
    'label_reviewbar' => 'reviewable',
    'label_approved' => 'approved',

    'status_bereinigen_meaning' => 'The PR is up but still being tidied — merge conflicts, open comments, red CI.',
    'status_bereinigen_does' => 'Counts as work, not as done: the task stays claimed and locked. Its PR is already recorded, though, so the gates of dependent tasks are open. It is not ready for review yet.',
    'status_bereinigen_set_by' => 'Not by the skill — the column belongs to the organization and is set by hand on the board or by an automation. The skill treats it as a work status: `/planstack work` carries a task on from here, `/planstack fix` polishes its PR.',
    'status_bereinigen_next' => '"Reviewable" — in the default configuration via the `POLISHED` event, once the CI is green and all comments are answered and resolved.',

    'status_reviewbar_meaning' => 'The pool ahead of "in review": the work is handed in and waits for somebody to take the review.',
    'status_reviewbar_does' => 'Does not count as done. `review-next` takes its tasks from exactly this pool, skipping the implementer. The server recognizes the pool by the `REVIEWABLE` role, not by the key — so the column may be named differently.',
    'status_reviewbar_set_by' => 'In the default configuration the `POLISHED` event. Taking over a review only sets `reviewed_by`; the move to "in review" comes from the org automation on the `REVIEWING` event, not from the endpoint.',
    'status_reviewbar_next' => '"In review", as soon as somebody takes the review.',

    'status_approved_meaning' => 'The review recommends the merge: approved, but not merged yet.',
    'status_approved_does' => 'Counts as done and as delivered — the progress jumps and the gates of dependent tasks are satisfied for good. The task is out of the review pool.',
    'status_approved_set_by' => 'An org automation on the `APPROVED` event, which `/planstack review` reports after an `APPROVE` recommendation. Without that automation the event stays a plain report and the status is unchanged.',
    'status_approved_next' => '"Merged" after the merge, or via the PR sync.',

    'status_merged_does' => 'Final state: counts as done, takes the task off the board and satisfies the gate of dependent tasks for good.',
    'status_merged_set_by' => 'The merge call (idempotent) or the server-side PR sync, when GitHub reports the PR as merged.',
    'status_merged_next' => 'Final state.',

    // Section 4: events
    'events_title' => 'Progress events',
    'events_hint' => 'Alongside the status changes the skill reports progress as events. Whether an event becomes a status change is up to the organization — without configuration it is just a report.',
    'events_best_effort' => 'Events are fire-and-forget: errors are ignored, the flow is never blocked, and sending them is not reported in prose.',
    'events_authoritative' => 'The response is authoritative, not the event name: it carries the actual status after the event. `status_changed:false` is not an error but means the guard did not match — then the returned status applies.',
    'events_merged_note' => 'The `MERGED` event is not reported by the skill but by the server during the PR sync.',
    'th_event' => 'Event',
    'th_when' => 'When',
    'th_effect' => 'Effect',
    'effect_status' => 'drives the status',
    'effect_info' => 'log only',
    'ev_claiming' => 'Before the claim — only when the id is already known, i.e. in single-task mode.',
    'ev_claimed' => 'After the claim.',
    'ev_analyzing' => 'Analysis begins.',
    'ev_analyzed' => 'Analysis done.',
    'ev_processing' => 'Implementation begins.',
    'ev_processed' => 'Implementation done.',
    'ev_publishing' => 'The PR is being created.',
    'ev_polishing' => 'Polishing begins (`fix`).',
    'ev_polished' => 'CI green, comments answered and resolved — the task becomes reviewable.',
    'ev_concerned' => 'A concern was reported.',
    'ev_reviewing' => 'Review taken over.',
    'ev_reviewed' => 'Review result recorded.',
    'ev_approved' => 'The recommendation was `APPROVE`.',
    'ev_changes_requested' => 'The recommendation was `REQUEST_CHANGES`.',
    'events_default_note' => 'The "effect" column shows the default configuration. What actually sets a status in this organization is laid down in the status rules.',
    // Event-driven steps of the lifecycle
    'lc_polishing' => 'Polishing begins: merge conflicts, open comments, red CI. `/planstack fix` works exactly here.',
    'lc_polished' => 'Polishing done — CI green, comments answered and resolved. This moves the task into the review pool.',
    'lc_approved' => 'The review recommends the merge. Reported by `/planstack review` after an `APPROVE` recommendation.',
    'lc_changes_requested' => 'The review asks for changes — the task goes back into implementation, the PR stays.',
    'lc_deployed' => 'Deployed. The final step after the merge, if the organization uses it.',

    // Further events
    'ev_unclaimed' => 'The claim was handed back — the task is free again.',
    'ev_merged' => 'PR merged. Not reported by the skill but by the server during the PR sync.',
    'ev_deployed' => 'Deployed.',
    'events_target_none' => 'log only',

    // Section: allowed status transitions
    'transitions_title' => 'Allowed status transitions',
    'transitions_hint' => 'A guarded state machine: only these transitions are permitted. Both dragging a card on the board and every action via API and MCP are checked against it.',
    'th_from' => 'from',
    'th_to' => 'allowed to',
    'transitions_note' => 'A forbidden transition is rejected with `409` — not silently dropped. From "blocked" and "concerned" almost every path leads back on purpose, so a task can never get stuck. Event-driven assignments run through their own guard set, not through this table.',

    // Section: field automations
    'fields_title' => 'Fields the server fills itself',
    'fields_hint' => 'On entering these statuses, fields are populated automatically — each only while still empty.',
    'th_on_status' => 'on entering',
    'th_fields' => 'fields set',
    'fields_note' => '`@actor` is the user behind the token, `@now` the moment of the transition. Because only empty fields are filled, entering the same status a second time overwrites nothing — the first claimer and the first reviewer are preserved.',
];
