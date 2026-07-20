<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kein allgemeingültiger Einladungscode mehr — Beitritt erfolgt nur noch
        // über individuelle Einladungen (organization_invitations). Auf bereits
        // migrierten Umgebungen die Spalte entfernen; auf frischen Installationen
        // existiert sie dank angepasster create-Migration gar nicht erst.
        if (Schema::hasColumn('organizations', 'invite_code')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('invite_code');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('organizations', 'invite_code')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->string('invite_code', 16)->nullable();
            });
        }
    }
};
