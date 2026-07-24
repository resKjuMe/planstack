<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Nutzer-Changelog der Website (Versionsübersicht, ehemals changelog.blade.php).
 * Die Releases stehen in config/changelog.php; hier werden sie für die aktuelle
 * Sprache aufbereitet (TL;DR-Keywords, formatiertes Datum, „Neu:"-Präfix als
 * Badge) und als Props übergeben. Die React-Seite hebt neue Releases per
 * localStorage hervor (wie zuvor das Inline-Script).
 */
class ChangelogController extends Controller
{
    public function __invoke(): InertiaResponse
    {
        $locale = app()->getLocale();
        $releases = collect(config('changelog.releases', []))->map(function ($release) use ($locale) {
            $tldr = $release['tldr'][$locale] ?? $release['tldr']['de'] ?? [];
            $changes = $release['changes'][$locale] ?? $release['changes']['de'] ?? [];

            return [
                'version' => $release['version'],
                'date' => ! empty($release['date'])
                    ? Carbon::parse($release['date'])->locale($locale)->translatedFormat('d. F Y')
                    : null,
                'tldr' => array_values($tldr),
                'changes' => collect($changes)->map(function ($change) {
                    // „Neu:"/„New:"-Präfix wird als Badge dargestellt statt als Text.
                    $isNew = Str::startsWith($change, ['Neu:', 'New:']);

                    return [
                        'isNew' => $isNew,
                        'text' => $isNew ? ltrim(Str::after($change, ':')) : $change,
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return Inertia::render('Changelog', [
            'releases' => $releases,
            'latestVersion' => config('changelog.releases.0.version'),
            'strings' => [
                'whatsNew' => __('changelog.what_s_new'),
                'intro' => __('changelog.all_visible_changes_to_planstack_newest'),
                'new' => __('changelog.new'),
                'noEntries' => __('changelog.no_entries_yet'),
            ],
        ]);
    }
}
