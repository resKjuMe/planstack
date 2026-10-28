<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wie wahrscheinlich der PO den PR vor dem GoLive sehen will (0–100).
        Schema::table('pull_request_reviews', function (Blueprint $table) {
            $table->unsignedTinyInteger('po_review_likelihood')->nullable()->after('ai_reviewability_reason');
            $table->string('po_review_likelihood_reason', 500)->nullable()->after('po_review_likelihood');
        });
    }

    public function down(): void
    {
        Schema::table('pull_request_reviews', function (Blueprint $table) {
            $table->dropColumn(['po_review_likelihood', 'po_review_likelihood_reason']);
        });
    }
};
