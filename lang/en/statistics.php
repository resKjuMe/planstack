<?php

return [
    'title' => 'Statistics',
    'subtitle' => 'Your own record across every project you can see.',
    'title_other' => 'Statistics: :name',
    'subtitle_other' => 'Record of :name across every project that person can see.',

    // Help
    'help_scope' => 'Scope',
    'help_scope_text' => 'Counted are the tasks CURRENTLY assigned to that person (field "claimed by"), across all projects visible to them. Releasing a task drops that attribution — this page shows the current state, not a history.',
    'help_delivered' => 'Delivered',
    'help_delivered_text' => 'Tasks in a status that counts as done (configured org-wide), plus their story points.',
    'help_cycle' => 'Cycle time',
    'help_cycle_text' => 'Median time from claim to merge. Median rather than average so one forgotten task does not skew everything.',
    'help_accuracy' => 'Estimation accuracy',
    'help_accuracy_text' => 'Share of delivered tasks whose actually changed file count deviates by at most 25 % from the estimate in the "affected files" field.',
    'help_volume' => 'Volume',
    'help_volume_text' => 'Actuals from the synced pull requests: files, lines, commits, comments. Without PR sync they stay empty.',
    'help_limits' => 'Limits',
    'help_limits_text' => 'Metrics without a data basis read "—". Story points are estimates, not working time — treat these numbers as a basis for conversation, not a report card.',

    // KPI tiles
    'kpi_delivered' => 'Delivered',
    'kpi_delivered_sub' => ':tasks task in :projects project|:tasks tasks in :projects projects',
    'kpi_open' => 'In progress',
    'kpi_open_sub' => ':sp SP open',
    'kpi_cycle' => 'Cycle time',
    'kpi_cycle_sub' => 'median claim → merge',
    'kpi_accuracy' => 'Accuracy ±25 %',
    'kpi_accuracy_sub' => ':hits of :total delivered tasks',

    // Sections
    'weekly_title' => 'Delivery per calendar week',
    'weekly_sub' => 'delivered story points over the last 12 weeks',
    'status_title' => 'My open tasks',
    'status_sub' => 'by status',
    'quality_title' => 'Quality',
    'volume_title' => 'Volume',
    'projects_title' => 'By project',
    'recent_title' => 'Recently delivered',

    // Metrics
    'm_velocity' => 'Velocity',
    'm_cycle_avg' => 'Cycle time (avg)',
    'm_time_per_sp' => 'Time per SP',
    'm_last_delivery' => 'Last delivery',
    'm_oldest_claim' => 'Oldest open claim',
    'm_median_deviation' => 'Median deviation',
    'm_approved' => 'Review approved',
    'm_request_changes' => 'Changes requested',
    'm_ci_failed' => 'Red CI runs',
    'm_open_threads' => 'Open review comments',
    'm_concerns' => 'Concerns reported',
    'm_critical' => 'Critical tasks',
    'm_reviews_given' => 'Reviews taken on',
    'm_reviewed_authors' => 'for people',
    'm_prs' => 'Pull requests',
    'm_files' => 'Changed files',
    'm_lines' => 'Lines',
    'm_commits' => 'Commits',
    'm_comments' => 'PR comments',
    'm_review_comments' => 'Review comments',
    'm_tokens' => 'Tokens',
    'm_man_days' => 'Person-days',

    // Tables
    'col_project' => 'Project',
    'col_delivered' => 'Delivered',
    'col_open' => 'Open',
    'col_cycle' => 'Cycle time',
    'col_volume' => 'Volume',
    'col_task' => 'Task',
    'col_merged' => 'Merged',
    'col_files' => 'Files (estimate → actual)',
    'col_deviation' => 'Deviation',

    // Empty states
    'empty_title' => 'No numbers yet',
    'empty_text' => 'As soon as the first task is claimed, the record starts building up here.',
    'empty_to_projects' => 'Go to projects',
    'no_open_tasks' => 'No open task — everything handed over.',
    'no_deliveries' => 'No delivered tasks yet.',

    // Units
    'unit_sp_week' => 'SP/wk',
    'unit_min' => 'min',
    'unit_hours' => 'h',
    'unit_days' => 'days',
    'of_total' => ':part of :total',
    'none' => '—',
];
