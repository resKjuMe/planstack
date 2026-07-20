<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            // Einladungscode zum Beitreten (eindeutig, ohne mehrdeutige Zeichen).
            $table->string('invite_code', 16)->unique();
            $table->timestamps();
        });

        // Jeder User gehört höchstens einer Organisation an ⇒ direkter FK auf users
        // (kein n:m). Wird die Organisation gelöscht, verlieren die Mitglieder nur
        // die Zugehörigkeit (nullOnDelete), sie werden nicht mitgelöscht.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')
                ->constrained('organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::dropIfExists('organizations');
    }
};
