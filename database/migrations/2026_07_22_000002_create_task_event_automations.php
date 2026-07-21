<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-organization, per-event automation (siehe docs/event-api.md). Für jedes
 * Fortschritts-Event (App\Enums\TaskEvent) kann eine Organisation festlegen:
 *  - target_status_id: in welchen Status die Aufgabe geschoben wird (NULL ⇒ der
 *    Status bleibt unverändert),
 *  - overridable_status_ids: welche aktuell gehaltenen Status überschrieben
 *    werden dürfen (leer/NULL ⇒ keine Einschränkung, immer überschreiben),
 *  - effects: zusätzliche Feld-Befüllungen ([{field, value, only_if_empty?}],
 *    gleiche Token-Semantik wie task_statuses.on_enter_effects).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_event_automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('event'); // App\Enums\TaskEvent value
            $table->foreignId('target_status_id')->nullable()
                ->constrained('task_statuses')->nullOnDelete();
            $table->json('overridable_status_ids')->nullable(); // [status_id, …]
            $table->json('effects')->nullable();                // [{field, value, only_if_empty?}]
            $table->timestamps();
            $table->unique(['organization_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_event_automations');
    }
};
