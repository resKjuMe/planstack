<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill: every task that is IN_PROGRESS but already has a PR is really
 * "Arbeit fertig, PR offen" → move it to the new IN_REVIEW state. Runs after
 * the enum was widened (…_add_in_review_to_task_status).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tasks')
            ->where('status', 'IN_PROGRESS')
            ->whereNotNull('pr_number')
            ->update(['status' => 'IN_REVIEW']);
    }

    public function down(): void
    {
        // Send the reviewed-with-PR tasks back to IN_PROGRESS (mirror of up()).
        DB::table('tasks')
            ->where('status', 'IN_REVIEW')
            ->whereNotNull('pr_number')
            ->update(['status' => 'IN_PROGRESS']);
    }
};
