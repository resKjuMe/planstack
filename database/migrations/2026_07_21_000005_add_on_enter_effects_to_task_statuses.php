<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-status "on enter" field effects (automatic assignments + field
 * population): when a task enters this status, apply these effects. Replaces the
 * earlier per-action task_status_automations approach with a per-status one that
 * the board can consume directly. Shape: [{field, value, only_if_empty?}] where
 * value may be a token (@actor, @now, @clear) or a literal.
 *
 * The now-unused task_status_automations table is left in place (deprecated) to
 * avoid a drop/recreate; it is no longer seeded or read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_statuses', function (Blueprint $table) {
            $table->json('on_enter_effects')->nullable()->after('counts_as_delivered');
        });
    }

    public function down(): void
    {
        Schema::table('task_statuses', function (Blueprint $table) {
            $table->dropColumn('on_enter_effects');
        });
    }
};
