<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-organization, configurable task statuses (Phase 1: additive only — these
 * tables are seeded but not yet read by the app; tasks.status stays the source
 * of truth until later phases). See app/Support/DefaultTaskStatuses.php for the
 * default set that mirrors today's fixed workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Collapse groups (must exist before task_statuses references them).
        Schema::create('task_status_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });

        Schema::create('task_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Semantic role (App\Enums\StatusRole value). NULL = custom status
            // with no wired action. MySQL treats multiple NULLs as distinct, so
            // the unique(organization_id, role) below still allows many customs
            // while pinning each action-role to exactly one status.
            $table->string('role')->nullable();
            $table->string('key');            // wire value, e.g. "IN_PROGRESS"
            $table->string('label');
            $table->string('label_en')->nullable();
            $table->string('kind');           // waiting|active|review|done|exception
            $table->string('color_token');    // finite palette token
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_column')->default(true);      // false ⇒ exception lane
            $table->boolean('default_expanded')->default(false);
            $table->unsignedInteger('wip_limit')->nullable();
            $table->boolean('counts_as_done')->default(false);
            $table->boolean('counts_as_delivered')->default(false);
            $table->foreignId('group_id')->nullable()->constrained('task_status_groups')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'role']);
            $table->unique(['organization_id', 'key']);
            $table->index(['organization_id', 'position']);
        });

        Schema::create('task_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_status_id')->constrained('task_statuses')->cascadeOnDelete();
            $table->foreignId('to_status_id')->constrained('task_statuses')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['from_status_id', 'to_status_id']);
            $table->index('from_status_id');
        });

        // Wired action → target status + field side effects (assignee/timestamps
        // and arbitrary allow-listed task fields). Mirrors today's hard-coded
        // effects; consumed from Phase 4 onward.
        Schema::create('task_status_automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('action');
            $table->foreignId('target_status_id')->constrained('task_statuses')->cascadeOnDelete();
            $table->json('effects')->nullable(); // [{field, value, only_if_empty?}]
            $table->timestamps();
            $table->unique(['organization_id', 'action']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            // Bumped on every status-config change; feeds the skill revision so
            // clients notice drift (status is org-scoped, config is project-scoped).
            $table->unsignedInteger('status_config_version')->default(1)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('status_config_version');
        });
        Schema::dropIfExists('task_status_automations');
        Schema::dropIfExists('task_status_transitions');
        Schema::dropIfExists('task_statuses');
        Schema::dropIfExists('task_status_groups');
    }
};
