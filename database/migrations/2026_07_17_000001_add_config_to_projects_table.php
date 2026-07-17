<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Board-Protokoll-Konfiguration (token-sparende Schalter): gespeichert
            // als {"profile": "...", "overrides": {...}}. Leer ⇒ historisches
            // Verhalten (siehe App\Support\ProjectConfig::DEFAULTS).
            $table->json('config')->nullable()->after('skill_description');
            // Wird bei jeder Konfigurationsänderung erhöht; reist als Header
            // X-Planstack-Config-Version mit, damit der Skill Drift erkennt.
            $table->unsignedInteger('config_version')->default(1)->after('config');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['config', 'config_version']);
        });
    }
};
