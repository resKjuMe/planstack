<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vom Nutzer manuell geprüfte PRs, mit dem Kopf-Commit zum Prüfzeitpunkt.
        Schema::create('pull_request_checks', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pull_request_id')->constrained('pull_requests')->cascadeOnDelete();
            $table->string('commit_sha')->nullable();
            $table->timestamp('checked_at')->nullable();

            $table->primary(['user_id', 'pull_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pull_request_checks');
    }
};
