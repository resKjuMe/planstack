<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feinere PR-Zustandsdaten aus demselben GraphQL-Poll (planstack:sync-pr-status):
     * die Aufschlüsselung der CI-Steps (failed/running/successful/waiting aus
     * statusCheckRollup.contexts) sowie der Merge-Queue-Status (isInMergeQueue +
     * mergeQueueEntry.state). Alle nullable — vor dem ersten Sync unbekannt.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('pr_ci_failed')->nullable()->after('pr_ci_status');
            $table->unsignedInteger('pr_ci_running')->nullable()->after('pr_ci_failed');
            $table->unsignedInteger('pr_ci_success')->nullable()->after('pr_ci_running');
            $table->unsignedInteger('pr_ci_waiting')->nullable()->after('pr_ci_success');
            $table->boolean('pr_in_merge_queue')->nullable()->after('pr_ci_waiting');
            // MergeQueueEntryState: AWAITING_CHECKS | LOCKED | MERGEABLE | QUEUED |
            // UNMERGEABLE (null, wenn der PR nicht in der Merge-Queue ist).
            $table->string('pr_merge_queue_state')->nullable()->after('pr_in_merge_queue');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'pr_ci_failed',
                'pr_ci_running',
                'pr_ci_success',
                'pr_ci_waiting',
                'pr_in_merge_queue',
                'pr_merge_queue_state',
            ]);
        });
    }
};
