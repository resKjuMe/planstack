<?php

use App\Models\Organization;
use App\Support\DefaultTaskStatuses;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill: give every existing organization the default task-status set. New
 * organizations are seeded by App\Observers\OrganizationObserver. Task rows are
 * NOT touched here (tasks.status → status_id migration follows in Phase 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Organization::query()->each(function (Organization $organization) {
            DefaultTaskStatuses::seed($organization);
        });
    }

    public function down(): void
    {
        // Remove seeded rows in FK-safe order (the create-tables migration's
        // down() drops the tables entirely; this handles a partial rollback).
        DB::table('task_status_automations')->delete();
        DB::table('task_status_transitions')->delete();
        DB::table('task_statuses')->delete();
        DB::table('task_status_groups')->delete();
    }
};
