<?php

return [
    // Collapse groups for folded areas
    'group_backlog' => 'Backlog',
    'group_in_work' => 'In progress',

    // Column header / collapse
    'collapse_column' => 'Collapse column',
    'expand_column' => 'Expand column',
    'wip_limit_title' => ':current of :limit (WIP limit :limit)',
    'wip_over_title' => 'WIP limit exceeded: :current of :limit',

    // MERGED limiting
    'show_all_merged' => 'Show all :count',
    'show_fewer' => 'Show fewer',
    'merged_hidden_hint' => 'Merged > :days days ago hidden',
    'show_old_merged' => 'Show older',
    'hide_old_merged' => 'Hide older',

    // Quick filters
    'filters' => 'Filters',
    'only_mine' => 'Only mine',
    'highlight_blocked' => 'Highlight blocked',
    'assignee' => 'Assignee',
    'assignee_all' => 'All',
    'assignee_unassigned' => 'Unassigned',
    'clear_filters' => 'Clear filters',
    'active_filter_remove' => 'Remove filter',
    'ungroup' => 'Ungroup columns',

    // Exception lane
    'exceptions_lane' => 'Blocked / Concerned',

    // Card
    'unassigned' => '—',
    'reviewer' => 'Reviewer',
    'approver' => 'Approver',
    'stacked' => 'Stacked on unfinished tasks',
    'badge_blocked' => 'Blocked',
    'badge_concerned' => 'Concerned',
    // PR status row (CI icon + unresolved comments)
    'ci_success' => 'CI passed',
    'ci_failure' => 'CI failed',
    'ci_pending' => 'CI running',
    'ci_unknown' => 'CI status unknown',
    'unresolved_comments' => 'Unresolved comments',
    'claim' => 'Claim',
    'release' => 'Release',

    // Drag-and-drop
    'move_error' => 'Status change rejected: :message',
    'move_forbidden' => 'Transition from :from to :to is not allowed.',
    'empty_column' => 'No tasks',

    // Loading the board tasks over the API
    'loading' => 'Loading board …',
    'load_error' => 'Could not load the board: :message',
];
