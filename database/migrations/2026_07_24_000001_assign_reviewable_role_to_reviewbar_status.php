<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Assign the new StatusRole::REVIEWABLE to existing organizations' REVIEWBAR
 * status. The review pickup flow (review-next/review-claim) anchors the "ready
 * for review" pool by ROLE, not key — before this, REVIEWBAR carried no role, so
 * review-next only ever saw IN_REVIEW and tasks waiting in REVIEWBAR were never
 * offered to reviewers.
 *
 * Only touches rows that still have the canonical REVIEWBAR key with no role, so
 * renamed/customized statuses are left alone. Bumps status_config_version on the
 * affected orgs so clients detect the status-config drift (mirrors what the
 * status-admin endpoints do on every change).
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('task_statuses')
            ->where('key', 'REVIEWBAR')
            ->whereNull('role')
            ->get(['id', 'organization_id']);

        if ($rows->isEmpty()) {
            return;
        }

        DB::table('task_statuses')
            ->whereIn('id', $rows->pluck('id'))
            ->update(['role' => 'REVIEWABLE', 'updated_at' => now()]);

        DB::table('organizations')
            ->whereIn('id', $rows->pluck('organization_id')->unique())
            ->increment('status_config_version');
    }

    public function down(): void
    {
        DB::table('task_statuses')
            ->where('key', 'REVIEWBAR')
            ->where('role', 'REVIEWABLE')
            ->update(['role' => null, 'updated_at' => now()]);
    }
};
