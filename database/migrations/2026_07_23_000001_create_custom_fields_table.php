<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-organization, benutzerdefinierte Task-Felder. Jede Organisation kann eigene
 * Felder definieren, die per API an Tasks befüllt werden (Wert liegt in
 * tasks.custom_fields, JSON, keyed by `key`).
 *
 *  - key: stabiler Maschinen-Schlüssel (pro Organisation eindeutig, API-Feldname),
 *  - label / label_en: Anzeigenamen,
 *  - type: Datentyp (string|text|integer|decimal|boolean|date|datetime|url|email),
 *  - validation: optionale Laravel-Validierungsregel (z. B. "required|max:100").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('label_en')->nullable();
            $table->string('type')->default('string');
            $table->text('validation')->nullable(); // Laravel rule string
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
