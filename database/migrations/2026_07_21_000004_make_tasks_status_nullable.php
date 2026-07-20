<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow tasks.status (the legacy ENUM) to be NULL, so a task that sits in a
 * custom, org-defined status (which has no canonical enum value) is valid.
 * status_id is the board authority from here on; the ENUM stays as a
 * best-effort mirror for canonical statuses and is dropped entirely in a later
 * phase.
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
            $table->enum('status', self::VALUES)->nullable()->default('UNKNOWN')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('status', self::VALUES)->default('UNKNOWN')->change();
        });
    }
};
