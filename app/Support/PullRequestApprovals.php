<?php

namespace App\Support;

use App\Models\PullRequest;
use App\Models\PullRequestApproval;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Approvals eines PR: letzte Entscheidung je Reviewer und die Pflicht-Approvals
 * je Gruppe kritischer Dateien (aus dem Check-Run des pr-bot).
 */
class PullRequestApprovals
{
    /** Jeder PR braucht mindestens so viele Approvals. */
    public const REQUIRED = 1;

    /**
     * Gruppen aus der Check-Summary: `| Backend | backend-dev | 1 / 2 | 6 | 1 missing |`.
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
     * @return array{approved: int, required: int, changesRequested: int, groups: ?array, fulfilled: bool}
     */
    public static function status(PullRequest $pullRequest, Collection $approvals): array
    {
        $approved = $approvals->where('state', PullRequestApproval::APPROVED)->count();
        $changesRequested = $approvals->where('state', PullRequestApproval::CHANGES_REQUESTED)->count();
        $groups = $pullRequest->approval_groups;

        return [
            'approved' => $approved,
            'required' => self::REQUIRED,
            'changesRequested' => $changesRequested,
            'groups' => $groups,
            'fulfilled' => $approved >= self::REQUIRED
                && $changesRequested === 0
                && collect($groups ?? [])->every(fn ($g) => $g['approved'] >= $g['required']),
        ];
    }
}
