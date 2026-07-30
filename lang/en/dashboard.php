<?php

return [
    'dashboard' => 'Dashboard',

    'title' => 'Dashboard',
    'greeting' => 'Hi :name',
    'subtitle' => 'Your work across all projects.',

    // KPI tiles
    'kpi_actionable' => 'On my plate',
    'kpi_actionable_sub' => ':sp SP · :tasks tasks',
    'kpi_reviews' => 'Reviews',
    'kpi_reviews_sub' => ':mine mine · :free still free',
    'kpi_week' => 'This week',
    'kpi_week_sub' => ':tasks delivered · :reviews reviewed',
    'kpi_oldest' => 'Waiting longest',
    'kpi_oldest_sub' => 'oldest item on my plate',

    // "On my plate"
    'my_work_title' => 'On my plate',
    'my_work_sub' => 'Like the board filter "On my plate", across all projects — your own work in review is listed separately.',
    'group_work' => 'My work steps',
    'group_work_hint' => 'Tasks you claimed that sit in a work step',
    'group_review' => 'Reviews',
    'group_review_hint' => "Reviews of other people's work that are yours or still free",
    'group_awaiting' => 'Awaiting review',
    'group_awaiting_hint' => 'Your own work in review — created or claimed by you. Reviewing your own work is not allowed, it waits for someone else.',
    'group_blocked' => 'Blocked & concerns',
    'group_blocked_hint' => 'Your own tasks in an exception — blocked or with a reported concern',
    'group_empty' => 'Nothing open.',
    'more_items' => '+ :count more',
    'filter_count' => ':count item|:count items',
    'all_clear_title' => 'Nothing on your plate.',
    'all_clear_text' => 'No work steps of your own, no open reviews, no exceptions. Unclaimed tasks are listed under “Free to pick”.',

    // Task row
    'free_review' => 'free',
    'assigned_to_me' => 'my review',
    'no_reviewer_yet' => 'no reviewer',
    'ci_failed' => 'CI red',
    'open_threads' => ':count open threads',
    'changes_requested' => 'changes requested',
    'since_hint' => 'Claimed or last changed',
    'created_by' => 'created by :name',
    'claimed_from' => 'by :name',
    'reviewer_is' => 'review: :name',
    'reviewer_open' => 'review: open',

    // Side panels
    'pickable_title' => 'Free to pick',
    'pickable_sub' => 'Unclaimed tasks with an open gate — the most blocking first.',
    'pickable_empty' => 'Nothing free — everything claimed or blocked.',
    'dependents' => ':count waiting on it',
    'projects_title' => 'My projects',
    'projects_sub' => 'Sorted by what needs you.',
    'project_mine' => ':count on my plate',
    'project_open' => ':count open',
    'activity_title' => 'Recent activity',
    'activity_sub' => 'Progress events from all visible projects.',
    'activity_empty' => 'No events yet.',
    'activity_you' => 'you',

    // Links + empty states
    'link_projects' => 'All projects',
    'link_statistics' => 'My statistics',
    'link_new_project' => 'New project',
    'empty_title' => 'No projects yet',
    'empty_text' => 'As soon as you have access to a project, this is where your open work shows up.',
];
