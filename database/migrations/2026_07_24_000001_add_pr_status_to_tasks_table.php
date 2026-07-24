<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Von GitHub gepollter PR-Zustand direkt am Task (je eine Spalte). Minütlich
     * von planstack:sync-pr-status (GraphQL) gefüllt, sobald der Task eine
     * pr_number trägt und sein Projekt ein github_repo hat. Grundlage der
     * serverseitigen „fix"-Erkennung des next-action-Resolvers.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('pr_node_id')->nullable()->after('pr_number');        // GraphQL-Global-ID des PR
            $table->string('pr_title', 512)->nullable()->after('pr_node_id');    // PR-Titel
            // statusCheckRollup.state: SUCCESS | FAILURE | PENDING | ERROR | EXPECTED
            // (null, wenn der Commit keine Checks hat).
            $table->string('pr_ci_status')->nullable()->after('pr_title');
            $table->unsignedInteger('pr_unresolved_threads')->nullable()->after('pr_ci_status');
            // reviewDecision: APPROVED | CHANGES_REQUESTED | REVIEW_REQUIRED | null.
            $table->string('pr_review_decision')->nullable()->after('pr_unresolved_threads');
            $table->timestamp('pr_last_commit_at')->nullable()->after('pr_review_decision');
            $table->timestamp('pr_status_synced_at')->nullable()->after('pr_last_commit_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'pr_node_id',
                'pr_title',
                'pr_ci_status',
                'pr_unresolved_threads',
                'pr_review_decision',
                'pr_last_commit_at',
                'pr_status_synced_at',
            ]);
        });
    }
};
