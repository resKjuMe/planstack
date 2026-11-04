<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_findings', function (Blueprint $table) {
            $table->unsignedTinyInteger('confidence')->nullable()->after('message');
            $table->string('confidence_reason', 500)->nullable()->after('confidence');
        });
    }

    public function down(): void
    {
        Schema::table('review_findings', function (Blueprint $table) {
            $table->dropColumn(['confidence', 'confidence_reason']);
        });
    }
};
