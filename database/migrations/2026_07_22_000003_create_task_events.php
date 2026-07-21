<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ereignis-Protokoll: jedes über POST /api/events gemeldete Fortschritts-Event
 * wird hier festgehalten (Zweck laut docs/event-api.md: den Fortschritt der
 * Abarbeitung sichtbar machen). Unabhängig davon, ob das Event eine Automation
 * ausgelöst hat — auch reine Meldungen ohne Konfiguration werden protokolliert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event'); // App\Enums\TaskEvent value
            $table->timestamps();
            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_events');
    }
};
