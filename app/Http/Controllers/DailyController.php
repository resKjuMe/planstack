<?php

namespace App\Http\Controllers;

use App\Support\DailyPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Stand-up-Ansicht (/daily): was ist am letzten Arbeitstag passiert. Ohne
 * `?date=` zeigt sie den Arbeitstag vor heute (montags also Freitag), `?scope=team`
 * schaltet von „Ich“ auf das ganze Team.
 */
class DailyController extends Controller
{
    public function __invoke(Request $request, DailyPresenter $presenter): InertiaResponse
    {
        $user = $request->user();
        $today = CarbonImmutable::now(DailyPresenter::TIMEZONE)->startOfDay();
        $day = $this->day($request->query('date'), $today);
        $scope = $request->query('scope') === 'team' ? 'team' : 'me';

        $next = DailyPresenter::nextWorkday($day);
        $link = fn (CarbonImmutable $date, string $scope) => route('daily', array_filter([
            'date' => $date->equalTo(DailyPresenter::previousWorkday($today)) ? null : $date->toDateString(),
            'scope' => $scope === 'team' ? 'team' : null,
        ]));

        $english = str_starts_with(app()->getLocale(), 'en');
        $localized = $day->locale(app()->getLocale());

        return Inertia::render('Daily', [
            'day' => [
                'date' => $day->toDateString(),
                'label' => $localized->isoFormat($english ? 'dddd, MMMM D, YYYY' : 'dddd, D. MMMM YYYY'),
                'short' => $localized->isoFormat($english ? 'ddd, MM/DD' : 'dd, DD.MM.'),
            ],
            'scope' => $scope,
            'me' => $user->name,
            'data' => $presenter->payload($user, $day, $scope),
            'urls' => [
                'profile' => route('profile.edit'),
                'prev' => $link(DailyPresenter::previousWorkday($day), $scope),
                'next' => $next->lessThanOrEqualTo($today) ? $link($next, $scope) : null,
                'me' => $link($day, 'me'),
                'team' => $link($day, 'team'),
            ],
            'strings' => __('daily'),
            'prStrings' => __('pull_requests'),
        ]);
    }

    private function day(mixed $date, CarbonImmutable $today): CarbonImmutable
    {
        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, DailyPresenter::TIMEZONE);
            if ($parsed !== false && $parsed->lessThanOrEqualTo($today)) {
                return $parsed;
            }
        }

        return DailyPresenter::previousWorkday($today);
    }
}
