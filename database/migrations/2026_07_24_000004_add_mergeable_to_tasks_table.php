<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Merge-Bar-Status des PR aus demselben GraphQL-Poll (planstack:sync-pr-status).
     * MergeableState: MERGEABLE | CONFLICTING | UNKNOWN (GitHub berechnet es async,
     * daher zeitweise UNKNOWN). „conflicted" = CONFLICTING.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('pr_mergeable')->nullable()->after('pr_merge_queue_state');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('pr_mergeable');
        });
    }
};
