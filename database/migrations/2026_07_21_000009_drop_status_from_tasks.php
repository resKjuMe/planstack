<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retire the legacy ENUM column tasks.status. status_id (FK → task_statuses) is
 * now the sole stored status; Task::status is a derived accessor (key → canonical
 * TaskStatus) with a mutator that resolves writes to status_id. All queries that
 * filtered on the column were moved to status_id / orgStatus role.
 */
return new class extends Migration
{
    private const VALUES = [
        'UNKNOWN', 'BLOCKED', 'CONCERNED', 'PICKABLE', 'CLAIMED',
        'ANALYZING', 'IN_PROGRESS', 'IN_REVIEW', 'COMPLETED', 'MERGED',
    ];

    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('status', self::VALUES)->nullable()->default('PICKABLE')->after('status_id');
        });

        // Best-effort backfill from the current status_id's canonical key.
        DB::statement(<<<'SQL'
            UPDATE tasks t
            JOIN task_statuses s ON s.id = t.status_id
            SET t.status = s.`key`
            WHERE s.`key` IN ('UNKNOWN','BLOCKED','CONCERNED','PICKABLE','CLAIMED','ANALYZING','IN_PROGRESS','IN_REVIEW','COMPLETED','MERGED')
        SQL);
    }
};
