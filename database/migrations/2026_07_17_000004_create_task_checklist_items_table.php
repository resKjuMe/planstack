<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            // acceptance | test — eine Tabelle für Akzeptanzkriterien und Testschritte.
            $table->string('kind');
            // Rolle/Rendering: AK: item|scope|done_when|contract; Test: step|expectation|hint.
            // Abhakbar: item, done_when, step, expectation. Read-only: scope, contract, hint.
            $table->string('role')->default('item');
            $table->unsignedInteger('position')->default(0);
            $table->longText('text');
            $table->boolean('checked')->default(false);
            $table->foreignId('checked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'kind', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_checklist_items');
    }
};
