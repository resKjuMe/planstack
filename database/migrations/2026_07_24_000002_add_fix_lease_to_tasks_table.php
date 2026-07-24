<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leichtes Lease für die „fix"-Aktion des next-action-Resolvers. Anders als
     * work (claim) und review (reviewed_by) hat „fix" keine natürliche Reservierung
     * — damit nicht zwei (parallele) Worker denselben PR reparieren, wird der Task
     * kurzzeitig geleast. Läuft das Lease ab (fix_lease_expires_at < now), gibt ein
     * toter Worker den PR automatisch wieder frei.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('fix_leased_by')->nullable()->after('pr_status_synced_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('fix_lease_expires_at')->nullable()->after('fix_leased_by');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fix_leased_by');
            $table->dropColumn('fix_lease_expires_at');
        });
    }
};
