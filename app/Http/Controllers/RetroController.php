<?php

namespace App\Http\Controllers;

use App\Support\DailyPresenter;
use App\Support\RetroPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Retro-Ansicht (/retro): Vorschläge für die Sprint-Retro. Standard ist der zuletzt
 * abgeschlossene Jira-Sprint, `?sprint=` wählt einen anderen. Ohne Jira-Sprint
 * gilt der Zeitraum `?weeks=` (1–4, Standard 2) bis `?until=` (Standard heute).
 * `?scope=team` zeigt das ganze Team.
 */
class RetroController extends Controller
{
    private const WEEKS = [1, 2, 3, 4];

    public function __invoke(Request $request, RetroPresenter $presenter): InertiaResponse
    {
        $today = CarbonImmutable::now(DailyPresenter::TIMEZONE)->startOfDay();
        $weeks = in_array((int) $request->query('weeks'), self::WEEKS, true) ? (int) $request->query('weeks') : 2;
        $until = $this->until($request->query('until'), $today);
        $scope = $request->query('scope') === 'team' ? 'team' : 'me';
        $sprintId = ctype_digit((string) $request->query('sprint')) ? (int) $request->query('sprint') : null;

        $data = $presenter->payload($request->user(), $scope, $sprintId, $until->subDays($weeks * 7 - 1)->startOfDay(), $until->endOfDay());
        $sprint = $data['sprint'];

        $periodLink = fn (CarbonImmutable $end, int $weeks, string $scope) => route('retro', array_filter([
            'until' => $end->equalTo($today) ? null : $end->toDateString(),
            'weeks' => $weeks === 2 ? null : $weeks,
            'scope' => $scope === 'team' ? 'team' : null,
        ]));
        $sprintLink = fn (?int $id, string $scope) => route('retro', array_filter([
            'sprint' => $id,
            'scope' => $scope === 'team' ? 'team' : null,
        ]));

        $next = $until->addDays($weeks * 7);
        $urls = $sprint !== null
            ? [
                'prev' => $sprint['prevId'] !== null ? $sprintLink($sprint['prevId'], $scope) : null,
                'next' => $sprint['nextId'] !== null ? $sprintLink($sprint['nextId'], $scope) : null,
                'me' => $sprintLink($sprintId, 'me'),
                'team' => $sprintLink($sprintId, 'team'),
                'weeks' => null,
            ]
            : [
                'prev' => $periodLink($until->subDays($weeks * 7), $weeks, $scope),
                'next' => $next->lessThanOrEqualTo($today) ? $periodLink($next, $weeks, $scope) : null,
                'me' => $periodLink($until, $weeks, 'me'),
                'team' => $periodLink($until, $weeks, 'team'),
                'weeks' => collect(self::WEEKS)->mapWithKeys(fn (int $w) => [$w => $periodLink($until, $w, $scope)])->all(),
            ];

        $format = str_starts_with(app()->getLocale(), 'en') ? 'MM/DD/YYYY' : 'DD.MM.YYYY';
        $from = CarbonImmutable::parse($data['period']['from']);
        $to = CarbonImmutable::parse($data['period']['until']);

        return Inertia::render('Retro', [
            'period' => [
                'label' => $from->isoFormat($format).' – '.$to->isoFormat($format),
                'weeks' => $weeks,
                'key' => $sprint !== null ? 'sprint-'.$sprint['id'] : $from->toDateString().'_'.$to->toDateString(),
            ],
            'scope' => $scope,
            'data' => $data,
            'urls' => [...$urls, 'profile' => route('profile.edit')],
            'strings' => __('retro'),
        ]);
    }

    private function until(mixed $date, CarbonImmutable $today): CarbonImmutable
    {
        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, DailyPresenter::TIMEZONE);
            if ($parsed !== false && $parsed->lessThanOrEqualTo($today)) {
                return $parsed;
            }
        }

        return $today;
    }
}
