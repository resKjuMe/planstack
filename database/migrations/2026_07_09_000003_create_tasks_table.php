<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('claimed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('summary', 255);
            $table->longText('description')->nullable();
            $table->unsignedBigInteger('phase_id')->nullable();
            $table->decimal('effort_man_days', 4, 1)->nullable();
            $table->integer('effort_story_points')->nullable();
            $table->integer('effort_tokens')->nullable();
            $table->unsignedInteger('affected_files')->nullable();
            $table->enum('status', [
                'UNKNOWN',
                'BLOCKED',
                'CONCERNED',
                'PICKABLE',
                'CLAIMED',
                'ANALYZING',
                'IN_PROGRESS',
                'COMPLETED',
                'MERGED',
            ])->default('UNKNOWN');
            $table->timestamps();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('merged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
