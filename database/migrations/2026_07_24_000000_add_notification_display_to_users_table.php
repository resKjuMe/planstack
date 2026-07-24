<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Darstellung der Benachrichtigungs-Glocke: 'dropdown' = klassisches
            // Flyout am Header-Icon (Default), 'sidebar' = dauerhaft ausgeklappte
            // Seitenleiste. Siehe resources/js/shell/AppShell.jsx.
            $table->string('notification_display', 16)->default('dropdown')->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_display');
        });
    }
};
