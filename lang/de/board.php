<?php

return [
    // Sammelgruppen für kollabierte Bereiche
    'group_backlog' => 'Backlog',
    'group_in_work' => 'In Arbeit',

    // Spaltenkopf / Collapse
    'collapse_column' => 'Spalte einklappen',
    'expand_column' => 'Spalte ausklappen',
    'wip_limit_title' => ':current von :limit (WIP-Limit :limit)',
    'wip_over_title' => 'WIP-Limit überschritten: :current von :limit',

    // MERGED-Begrenzung
    'show_all_merged' => 'Alle :count anzeigen',
    'show_fewer' => 'Weniger anzeigen',
    'merged_hidden_hint' => 'Gemergt vor > :days Tagen ausgeblendet',
    'show_old_merged' => 'Ältere einblenden',
    'hide_old_merged' => 'Ältere ausblenden',

    // Quick-Filter
    'filters' => 'Filter',
    'only_mine' => 'Nur meine',
    'highlight_blocked' => 'Blockiert hervorheben',
    'assignee' => 'Zuständig',
    'assignee_all' => 'Alle',
    'assignee_unassigned' => 'Niemand',
    'clear_filters' => 'Filter zurücksetzen',
    'active_filter_remove' => 'Filter entfernen',
    'ungroup' => 'Gruppen auflösen',

    // Ausnahme-Leiste
    'exceptions_lane' => 'Blockiert / Problematisch',

    // Karte
    'unassigned' => '—',
    'reviewer' => 'Reviewer',
    'approver' => 'Approver',
    'stacked' => 'Gestapelt auf noch nicht abgeschlossene Tasks',
    'badge_blocked' => 'Blockiert',
    'badge_concerned' => 'Problematisch',
    // PR-Zustandszeile (CI-Icon + ungelöste Kommentare)
    'ci_success' => 'CI erfolgreich',
    'ci_failure' => 'CI fehlgeschlagen',
    'ci_pending' => 'CI läuft',
    'ci_unknown' => 'CI-Status unbekannt',
    'unresolved_comments' => 'Ungelöste Kommentare',
    'pr_conflict' => 'Approved & CI grün, aber Merge-Konflikt',
    'pr_no_repo' => 'Nicht verlinkbar: im Projekt ist kein GitHub-Repository konfiguriert',
    'claim' => 'Beanspruchen',
    'release' => 'Freigeben',

    // Kopier-Menü der Karte
    'copy' => 'Kopieren',
    'copied' => 'Kopiert',
    'copy_task_name' => 'Task-Name',
    'copy_project_task_name' => 'Projekt + Task-Name',
    'copy_task_url' => 'Task-URL',
    'copy_work_command' => 'Work-Command',
    'copy_fix_command' => 'Fix-Command',
    'copy_review_command' => 'Review-Command',
    'start_with_claude' => 'In Claude starten (:command)',
    'claudetask_not_registered' => 'Claude ließ sich nicht starten.',
    'claudetask_clipboard_fallback' => 'Vermutlich ist der claudetask:-Handler nicht registriert. Der Prompt liegt in der Zwischenablage.',
    'claudetask_setup_link' => 'So richtest du den Handler ein',

    // Drag-and-drop
    'move_error' => 'Statuswechsel abgelehnt: :message',
    'move_forbidden' => 'Übergang von :from nach :to ist nicht erlaubt.',
    'empty_column' => 'Keine Aufgaben',

    // Laden der Board-Tasks über die API
    'loading' => 'Board wird geladen …',
    'load_error' => 'Board konnte nicht geladen werden: :message',
];
