<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pull_request_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_id')->constrained('pull_requests')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 20);
            $table->unsignedInteger('findings')->default(0);
            $table->unsignedInteger('findings_critical')->default(0);
            $table->unsignedInteger('findings_important')->default(0);
            $table->unsignedInteger('findings_hint')->default(0);
            $table->unsignedTinyInteger('criticality');
            $table->string('criticality_reason', 500)->nullable();
            $table->unsignedTinyInteger('ai_reviewability');
            $table->string('ai_reviewability_reason', 500)->nullable();
            $table->longText('summary')->nullable();
            $table->string('commit_sha', 40)->nullable();
            $table->string('strictness', 20)->nullable();
            $table->string('thoroughness', 20)->nullable();
            $table->string('model', 100)->nullable();
            $table->timestamps();

            $table->index(['pull_request_id', 'created_at']);
            $table->index('reviewer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pull_request_reviews');
    }
};
