<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Verknüpfung mit Jira: Projekt ↔ Epic, Phase ↔ Aufgabe, Task ↔ Unteraufgabe.
        Schema::table('projects', function (Blueprint $table) {
            $table->string('jira_epic_key', 32)->nullable()->after('github_repo');
        });
        Schema::table('phases', function (Blueprint $table) {
            $table->string('jira_key', 32)->nullable()->after('position');
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('jira_key', 32)->nullable()->after('criticality');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('jira_key');
        });
        Schema::table('phases', function (Blueprint $table) {
            $table->dropColumn('jira_key');
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('jira_epic_key');
        });
    }
};
