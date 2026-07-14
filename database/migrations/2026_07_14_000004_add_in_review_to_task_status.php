<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add the IN_REVIEW lifecycle state (Arbeit fertig, PR offen, wartet auf Merge)
 * between IN_PROGRESS and COMPLETED. Status is a MySQL enum column, so the value
 * set has to be widened with a MODIFY COLUMN.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('UNKNOWN','BLOCKED','CONCERNED','PICKABLE','CLAIMED','ANALYZING','IN_PROGRESS','IN_REVIEW','COMPLETED','MERGED') NOT NULL DEFAULT 'UNKNOWN'");
    }

    public function down(): void
    {
        // Fold any IN_REVIEW rows back to IN_PROGRESS before the value disappears.
        DB::statement("UPDATE tasks SET status = 'IN_PROGRESS' WHERE status = 'IN_REVIEW'");
        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('UNKNOWN','BLOCKED','CONCERNED','PICKABLE','CLAIMED','ANALYZING','IN_PROGRESS','COMPLETED','MERGED') NOT NULL DEFAULT 'UNKNOWN'");
    }
};
