<?php

namespace App\Support;

use App\Models\PullRequest;
use App\Models\PullRequestApproval;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Approvals eines PR: letzte Entscheidung je Reviewer und die Pflicht-Approvals
 * aus dem Check-Run des pr-bot.
 */
class PullRequestApprovals
{
    /** Pflicht-Approvals, solange der pr-bot keine Anforderung liefert. */
    public const REQUIRED = 1;

    /**
     * Anforderung aus der Summary von „pr-bot / Review Requirement“: Stufe,
     * Pflicht-Approvals (`**Approvals:** 0 von 2`, bei trivial `keine nötig`) und berührte Critical-Files-Gruppen.
     * null, wenn die Summary fehlt oder nicht erkennbar ist.
     *
     * @return array{level: string, required: int, criticalGroups: array<int, string>}|null
     */
    public static function parseRequirement(?string $summary): ?array
    {
        if ($summary === null
            || ! preg_match('/\*\*Stufe:\*\*\s*`([a-z]+)`/', $summary, $level)
            || ! preg_match('/\*\*Approvals:\*\*\s*(?:\d+\s+von\s+(\d+)|keine nötig)/u', $summary, $required)) {
            return null;
        }

        preg_match_all('/^-\s*Gruppe\s+\*\*([^*]+)\*\*:/m', $summary, $groups);

        return [
            'level' => $level[1],
            'required' => (int) ($required[1] ?? 0),
            'criticalGroups' => array_values(array_unique($groups[1])),
        ];
    }

    /**
     * Gruppen aus der Summary des früheren Checks „pr-bot / Check Critical Files
     * Approvals“: `| Backend | backend-dev | 1 / 2 | 6 | 1 missing |`.
     * null, wenn die Summary fehlt oder nicht erkennbar ist; [] ohne betroffene Gruppe.
     *
     * @return array<int, array{label: string, team: string, approved: int, required: int}>|null
     */
    public static function parseGroups(?string $summary): ?array
    {
        if ($summary === null || trim($summary) === '') {
            return null;
        }

        if (str_contains($summary, 'touches no critical files')) {
            return [];
        }

        preg_match_all('/^\|\s*([^|]+?)\s*\|\s*([^|]*?)\s*\|\s*(\d+)\s*\/\s*(\d+)\s*\|/m', $summary, $rows, PREG_SET_ORDER);

        if ($rows === []) {
            return null;
        }

        return array_map(fn (array $row) => [
            'label' => $row[1],
            'team' => $row[2],
            'approved' => (int) $row[3],
            'required' => (int) $row[4],
        ], $rows);
    }

    /**
     * Ersetzt die Approvals durch die letzte Entscheidung je Reviewer. Kommentare
     * zählen nicht, ein DISMISSED hebt die vorherige Entscheidung auf, Reviews des
     * Autors fallen weg.
     *
     * @param  iterable<int, array{login: ?string, state: ?string, submitted_at?: ?string, commit_sha?: ?string}>  $reviews
     */
    public static function sync(PullRequest $pullRequest, iterable $reviews): void
    {
        $latest = collect($reviews)
            ->filter(fn ($r) => ! empty($r['login']) && in_array($r['state'] ?? null, [PullRequestApproval::APPROVED, PullRequestApproval::CHANGES_REQUESTED, 'DISMISSED'], true))
            ->reject(fn ($r) => strcasecmp((string) $r['login'], (string) $pullRequest->author_login) === 0)
            ->sortBy(fn ($r) => $r['submitted_at'] ?? '')
            ->keyBy('login')
            ->reject(fn ($r) => $r['state'] === 'DISMISSED');

        DB::transaction(function () use ($pullRequest, $latest) {
            $pullRequest->approvals()->whereNotIn('login', $latest->keys()->all())->delete();

            foreach ($latest as $login => $review) {
                $pullRequest->approvals()->updateOrCreate(['login' => $login], [
                    'user_id' => PullRequest::resolveAuthor($pullRequest->organization_id, $login)?->id,
                    'state' => $review['state'],
                    'submitted_at' => isset($review['submitted_at']) ? Carbon::parse($review['submitted_at']) : null,
                    'commit_sha' => $review['commit_sha'] ?? null,
                ]);
            }
        });
    }

    /**
     * Stand für die Anzeige.
     *
     * @param  Collection<int, PullRequestApproval>  $approvals
     * @return array{approved: int, required: int, changesRequested: int, level: ?string, criticalGroups: array<int, string>, groups: ?array, fulfilled: bool}
     */
    public static function status(PullRequest $pullRequest, Collection $approvals): array
    {
        $approved = $approvals->where('state', PullRequestApproval::APPROVED)->count();
        $changesRequested = $approvals->where('state', PullRequestApproval::CHANGES_REQUESTED)->count();
        $requirement = $pullRequest->review_requirement;
        $required = $requirement['required'] ?? self::REQUIRED;
        $groups = $pullRequest->approval_groups;

        return [
            'approved' => $approved,
            'required' => $required,
            'changesRequested' => $changesRequested,
            'level' => $requirement['level'] ?? null,
            'criticalGroups' => $requirement['criticalGroups'] ?? [],
            'groups' => $groups,
            'fulfilled' => $approved >= $required
                && $changesRequested === 0
                && collect($groups ?? [])->every(fn ($g) => $g['approved'] >= $g['required']),
        ];
    }
}
