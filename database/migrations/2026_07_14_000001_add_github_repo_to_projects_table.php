<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // "owner/repo" für PR-Verlinkung und PR-Status-Sync. Überschreibt den
            // per-Alias-Default aus config/planstack.php ("github_repos"), sodass
            // ein Repo direkt am Projekt statt nur im Deployment gepflegt werden kann.
            $table->string('github_repo')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('github_repo');
        });
    }
};
