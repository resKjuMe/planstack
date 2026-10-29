<?php

namespace App\Support;

use App\Models\PullRequest;
use App\Models\ReviewFinding;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Hält einen bewusst nicht gefixten Befund mit Begründung im PR-Body fest —
 * unter „## Review-Entscheidungen“ › „### Nicht umgesetzt“, wie es auch der
 * Fix-Lauf von ps-review pflegt.
 */
class PullRequestSkippedFindings
{
    private const SECTION = '## Review-Entscheidungen';

    private const SUBSECTION = '### Nicht umgesetzt';

    /**
     * Schreibt den Eintrag auf GitHub und gibt den neuen Body zurück.
     *
     * @throws RuntimeException ohne Token oder wenn GitHub den Body nicht liefert bzw. annimmt
     */
    public function record(PullRequest $pr, ReviewFinding $finding, string $reason, User $user): string
    {
        $token = config('planstack.github_token');
        if (empty($token)) {
            throw new RuntimeException(__('pull_requests.fix_skip_no_token'));
        }

        $client = Http::baseUrl(rtrim((string) config('planstack.github_api'), '/'))
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'planstack'])
            ->withOptions(['verify' => config('planstack.github_verify_ssl', true)])
            ->withToken((string) $token)
            ->timeout(20);
        $path = "repos/{$pr->repository}/pulls/{$pr->pull_request_id}";

        // Aktuellen Body holen, damit parallele Änderungen auf GitHub nicht verloren gehen.
        $current = $this->send(fn () => $client->get($path));
        $body = self::withSkippedFinding((string) ($current['body'] ?? ''), self::bullet($finding, $reason, $user));
        $this->send(fn () => $client->patch($path, ['body' => $body]));

        return $body;
    }

    public static function bullet(ReviewFinding $finding, string $reason, User $user): string
    {
        $level = __('pull_requests.level_'.$finding->level->value);
        $meta = $finding->requires_po_decision ? "{$level}, ".__('pull_requests.fix_po_note') : $level;
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));

        return "- **{$finding->title}** ({$meta}): {$reason} — {$user->name}, ".now()->format('d.m.Y');
    }

    /**
     * Hängt den Eintrag an „### Nicht umgesetzt“ an; fehlt der Abschnitt, wird er
     * angelegt — vor einer abschließenden Attributionszeile, sonst ans Ende.
     */
    public static function withSkippedFinding(string $body, string $bullet): string
    {
        $body = rtrim(str_replace("\r\n", "\n", $body));

        if ($end = self::sectionEnd($body, '/^'.preg_quote(self::SUBSECTION, '/').'[ \t]*$/mu', '/^(#{1,3} |🤖 )/mu')) {
            return self::insert($body, $end, $bullet);
        }

        if ($end = self::sectionEnd($body, '/^'.preg_quote(self::SECTION, '/').'[ \t]*$/mu', '/^(#{1,2} |🤖 )/mu')) {
            return self::insert($body, $end, self::SUBSECTION."\n\n".$bullet);
        }

        $end = preg_match('/^🤖 /mu', $body, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : strlen($body);

        return self::insert($body, $end, self::SECTION."\n\n".self::SUBSECTION."\n\n".$bullet);
    }

    /**
     * Byte-Position, an der der Abschnitt mit der Überschrift endet; null, wenn es ihn nicht gibt.
     */
    private static function sectionEnd(string $body, string $heading, string $next): ?int
    {
        if (! preg_match($heading, $body, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $from = $m[0][1] + strlen($m[0][0]);

        return preg_match($next, $body, $n, PREG_OFFSET_CAPTURE, $from) ? $n[0][1] : strlen($body);
    }

    private static function insert(string $body, int $at, string $block): string
    {
        $before = rtrim(substr($body, 0, $at));
        $after = trim(substr($body, $at));
        $lastLine = substr($before, (int) strrpos("\n".$before, "\n"));
        // Ein Listeneintrag schließt ohne Leerzeile an die vorhandene Liste an.
        $glue = $before === '' ? '' : (str_starts_with($block, '- ') && str_starts_with($lastLine, '- ') ? "\n" : "\n\n");

        return $before.$glue.$block.($after === '' ? '' : "\n\n".$after);
    }

    /**
     * @param  callable(): Response  $request
     */
    private function send(callable $request): array
    {
        try {
            $response = $request();
        } catch (ConnectionException $e) {
            throw new RuntimeException(__('pull_requests.fix_skip_github_failed', ['status' => $e->getMessage()]), previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException(__('pull_requests.fix_skip_github_failed', ['status' => $response->status()]));
        }

        return (array) $response->json();
    }
}
