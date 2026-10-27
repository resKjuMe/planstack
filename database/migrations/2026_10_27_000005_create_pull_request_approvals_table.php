<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Letzte Entscheidung je GitHub-Reviewer (APPROVED / CHANGES_REQUESTED).
        Schema::create('pull_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_id')->constrained('pull_requests')->cascadeOnDelete();
            $table->string('login', 100);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('state', 20);
            $table->timestamp('submitted_at')->nullable();
            $table->string('commit_sha', 40)->nullable();

            $table->unique(['pull_request_id', 'login']);
        });

        Schema::table('pull_requests', function (Blueprint $table) {
            // [{label, team, approved, required}]; null = unbekannt, [] = keine Gruppe betroffen.
            $table->json('approval_groups')->nullable()->after('review_comments');
        });
    }

    public function down(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->dropColumn('approval_groups');
        });

        Schema::dropIfExists('pull_request_approvals');
    }
};
