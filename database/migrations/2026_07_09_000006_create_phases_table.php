<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // tasks.phase_id was created loose (per spec); wire up the FK now that phases exists.
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('phase_id')->references('id')->on('phases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['phase_id']);
        });

        Schema::dropIfExists('phases');
    }
};
