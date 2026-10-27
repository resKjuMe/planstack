<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vom Autor als „Shared Review“ markiert: sichtbar für die ganze Organisation.
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->timestamp('shared_at')->nullable()->after('closed_at');
            $table->foreignId('shared_by_id')->nullable()->after('shared_at')->constrained('users')->nullOnDelete();
            $table->index(['organization_id', 'shared_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'shared_at']);
            $table->dropConstrainedForeignId('shared_by_id');
            $table->dropColumn('shared_at');
        });
    }
};
