<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Sprechender URL-Schlüssel je Nutzer („christian-mietze") für persönliche
     * Seiten wie /{user}/stats. Aus dem Namen abgeleitet, bei Gleichnamigkeit mit
     * Zähler-Suffix. Nullable, damit die Migration auf Bestandsdaten läuft; für
     * neue/umbenannte Nutzer setzt {@see User::syncSlug()} den Wert.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        $taken = [];

        foreach (DB::table('users')->orderBy('id')->get(['id', 'name', 'email']) as $user) {
            // Fällt der Name komplett weg (nur Sonderzeichen), trägt der lokale
            // Teil der E-Mail die URL — irgendein stabiler Schlüssel muss es sein.
            $base = Str::slug($user->name) ?: Str::slug(Str::before($user->email, '@')) ?: 'user';

            $slug = $base;
            $suffix = 2;
            while (isset($taken[$slug])) {
                $slug = $base.'-'.$suffix++;
            }
            $taken[$slug] = true;

            DB::table('users')->where('id', $user->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
