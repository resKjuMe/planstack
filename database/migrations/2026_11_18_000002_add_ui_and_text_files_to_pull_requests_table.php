<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anzahl geänderter Frontend- bzw. Text-/Übersetzungsdateien je PR, gezählt beim
 * GitHub-Sync. Grundlage der Karte „Für UX“ im Daily; null = noch nie gezählt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->unsignedSmallInteger('ui_files')->nullable()->after('changed_files');
            $table->unsignedSmallInteger('text_files')->nullable()->after('ui_files');
        });
    }

    public function down(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->dropColumn(['ui_files', 'text_files']);
        });
    }
};
