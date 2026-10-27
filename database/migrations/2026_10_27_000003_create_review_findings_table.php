<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_review_id')->constrained('pull_request_reviews')->cascadeOnDelete();
            $table->string('level', 10);
            $table->string('type', 10);
            $table->boolean('requires_po_decision')->default(false);
            $table->string('file', 500)->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->string('title', 300);
            $table->longText('message');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_findings');
    }
};
