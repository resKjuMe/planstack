<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Persönlicher Jira-Zugang (Token verschlüsselt) für den serverseitigen Abgleich.
        Schema::table('users', function (Blueprint $table) {
            $table->string('jira_base_url')->nullable();
            $table->string('jira_email')->nullable();
            $table->text('jira_api_token')->nullable();
        });
        // Mit wessen Jira-Zugang Status/Assignee des Projekts abgeglichen werden.
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('jira_sync_user_id')->nullable()->after('jira_epic_key')
                ->constrained('users')->nullOnDelete();
        });
        // Zuletzt gemeinsamer Stand von Planstack und Jira (Status-Klasse, Assignee)
        // als Basis des 3-Wege-Abgleichs.
        Schema::table('tasks', function (Blueprint $table) {
            $table->json('jira_sync_base')->nullable()->after('jira_key');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('jira_sync_base');
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jira_sync_user_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['jira_base_url', 'jira_email', 'jira_api_token']);
        });
    }
};
