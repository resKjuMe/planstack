<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: introduce tasks.status_id (FK → task_statuses) and backfill it from
 * the existing ENUM status. The ENUM column stays the authority for now; a
 * dual-write hook on the Task model keeps status_id in sync (see Task::booted()).
 *
 * The retired UNKNOWN ("ausstehend") maps to the PICKABLE status, matching the
 * default seed which has no UNKNOWN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('status_id')->nullable()->after('status')
                ->constrained('task_statuses')->nullOnDelete();
            $table->index('status_id');
        });

        // Backfill via the task's project → organization → status of matching key
        // (UNKNOWN → PICKABLE). Single set-based UPDATE (MySQL JOIN syntax).
        DB::statement(<<<'SQL'
            UPDATE tasks t
            JOIN projects p ON p.id = t.project_id
            JOIN task_statuses s
              ON s.organization_id = p.organization_id
             AND s.`key` = CASE WHEN t.status = 'UNKNOWN' THEN 'PICKABLE' ELSE t.status END
            SET t.status_id = s.id
            WHERE t.status_id IS NULL
        SQL);

        $unmatched = DB::table('tasks')->whereNull('status_id')->count();
        if ($unmatched > 0) {
            Log::warning("add_status_id_to_tasks: {$unmatched} task(s) without a matching org status_id (kept NULL).");
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropIndex(['status_id']);
            $table->dropColumn('status_id');
        });
    }
};
