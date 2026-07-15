<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_pull_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            // PR-Nummer aus der GitHub-URL, nur innerhalb des Repos eindeutig
            // (das Repo ergibt sich über task->project->github_repo).
            $table->unsignedInteger('pull_request_id');
            $table->unsignedInteger('changed_files')->default(0);
            $table->unsignedInteger('additions')->default(0);
            $table->unsignedInteger('deletions')->default(0);
            $table->unsignedInteger('commits')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('review_comments')->default(0);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'pull_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_pull_requests');
    }
};
