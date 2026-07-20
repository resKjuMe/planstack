<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            // Individueller, einmaliger Token je Einladung (Registrierungslink).
            $table->string('token', 64)->unique();
            // Teams, denen die Person nach der Registrierung zugeordnet wird.
            $table->json('team_ids')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');
    }
};
