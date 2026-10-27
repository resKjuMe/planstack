<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pull_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('repository', 200);
            // PR-Nummer, nur zusammen mit `repository` eindeutig.
            $table->unsignedInteger('pull_request_id');
            // Planstack-User zum GitHub-Login (`<slug>_clockodo`), sonst NULL.
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_login', 100)->nullable();
            $table->string('title', 500);
            $table->string('branch', 255);
            $table->string('target_branch', 255);
            $table->string('last_commit', 40)->nullable();
            $table->longText('description')->nullable();
            $table->string('state', 10)->default('OPEN');
            $table->unsignedInteger('changed_files')->default(0);
            $table->unsignedInteger('additions')->default(0);
            $table->unsignedInteger('deletions')->default(0);
            $table->unsignedInteger('commits')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('review_comments')->default(0);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'repository', 'pull_request_id']);
            $table->index(['organization_id', 'state']);
            $table->index(['state', 'repository']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pull_requests');
    }
};
