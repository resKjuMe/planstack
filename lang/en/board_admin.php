<?php

return [
    'title' => 'Manage task statuses',
    'intro' => 'Customize your organization\'s statuses: label, color, order, whether it is a column or an exception lane, whether the column is expanded by default, and an optional WIP limit. The board applies changes immediately.',
    'deferred_note' => 'Creating and deleting additional (custom) statuses will follow in a later step, once tasks can be assigned to them.',
    'back_to_organization' => 'Back to organization',

    'statuses' => 'Statuses',
    'col_status' => 'Status',
    'col_key' => 'Key',
    'col_role' => 'Role',
    'col_kind' => 'Kind',
    'col_label' => 'Label (DE)',
    'col_label_en' => 'Label (EN)',
    'col_color' => 'Color',
    'col_position' => 'Position',
    'col_is_column' => 'Column',
    'col_expanded' => 'Open by default',
    'col_wip' => 'WIP limit',
    'col_actions' => '',
    'save' => 'Save',
    'status_saved' => 'Status “:label” saved.',

    'kind_waiting' => 'waiting',
    'kind_active' => 'in progress',
    'kind_review' => 'review',
    'kind_done' => 'done',
    'kind_exception' => 'exception',

    'transitions_title' => 'Allowed transitions',
    'transitions_intro' => 'For each status, choose which target statuses it may move to via drag-and-drop. Disallowed targets are not a drop target on the board.',
    'transitions_from' => 'From \\ To',
    'save_transitions' => 'Save transitions',
    'transitions_saved' => 'Transitions saved.',

    'manage_link' => 'Manage task statuses',

    'col_group' => 'Group',
    'no_group' => '— none —',
    'groups_title' => 'Collapse groups',
    'groups_intro' => 'Fold consecutive collapsed columns into one bar (e.g. “In progress”). A group only appears when at least two of its columns are collapsed.',
    'group_label' => 'Group label',
    'add_group' => 'Add group',
    'delete_group' => 'Delete',
    'group_saved' => 'Group “:label” saved.',
    'group_deleted' => 'Group deleted.',
    'no_groups' => 'No groups yet.',
];
