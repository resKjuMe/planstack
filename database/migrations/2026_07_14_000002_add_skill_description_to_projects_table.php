<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Freitext (Markdown) mit der Skill-Beschreibung des Projekts – wird
            // im .md-Editor der Projekt-Formulare gepflegt.
            $table->text('skill_description')->nullable()->after('github_repo');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('skill_description');
        });
    }
};
