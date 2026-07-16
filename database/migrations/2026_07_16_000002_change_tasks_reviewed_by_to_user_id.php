<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * tasks.reviewed_by held a free-text reviewer name; switch it to a nullable
 * foreign key on users. Existing names are translated to their user id via a
 * name match (unmatched names fall back to NULL) so no data is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Temporary int column beside the string one.
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('reviewed_by_id')->nullable()->after('reviewed_by');
        });

        // 2. Translate reviewer names to user ids (no match → stays NULL).
        DB::statement('UPDATE tasks t JOIN users u ON u.name = t.reviewed_by SET t.reviewed_by_id = u.id');

        // 3. Drop the string column and let the int column take its place.
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('reviewed_by');
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->renameColumn('reviewed_by_id', 'reviewed_by');
        });

        // 4. Now a proper nullable FK to users (reviewer deletion → NULL).
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Reverse: FK int column back to the free-text reviewer name.
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('reviewed_by_name')->nullable()->after('reviewed_by');
        });
        DB::statement('UPDATE tasks t JOIN users u ON u.id = t.reviewed_by SET t.reviewed_by_name = u.name');
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('reviewed_by');
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->renameColumn('reviewed_by_name', 'reviewed_by');
        });
    }
};
