<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eingehende GitHub-Webhooks (POST /hooks/git). Vorerst reines Protokoll: jeder
 * Aufruf wird roh festgehalten, damit die spätere Ereignisverarbeitung (PR-,
 * CI-, Review-, Kommentar-Ereignisse → Task-Status/Automationen) auf echten
 * Nutzlasten aufsetzen kann. Repo und PR-Nummer werden – wo aus der Nutzlast
 * ableitbar – zum passenden Task/Projekt aufgelöst; unauflösbare Ereignisse
 * (push, create/delete, workflow_job …) werden trotzdem protokolliert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('git_webhook_events', function (Blueprint $table) {
            $table->id();
            // X-GitHub-Event-Header, z. B. pull_request, workflow_run, push.
            $table->string('event');
            // payload.action, z. B. opened, synchronize, completed (null bei push/create/delete).
            $table->string('action')->nullable();
            // X-GitHub-Delivery (GUID) – eindeutige Zustell-ID zur Deduplizierung.
            $table->string('delivery_id')->nullable();
            // repository.full_name, z. B. acme/widgets.
            $table->string('repository')->nullable();
            // PR-Nummer, wo ableitbar (pull_request, review, issue_comment auf PR, workflow_run).
            $table->unsignedInteger('pr_number')->nullable();
            // Best-effort aufgelöst über repository.full_name = project.github_repo.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            // Best-effort aufgelöst über (repository, pr_number) → task.pr_number.
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            // Rohe Nutzlast, damit die spätere Verarbeitung nichts verliert.
            $table->json('payload');
            $table->timestamps();

            $table->index('delivery_id');
            $table->index(['event', 'action']);
            $table->index(['repository', 'pr_number']);
            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('git_webhook_events');
    }
};
