<?php

namespace App\Support;

/**
 * Per-project board protocol configuration: the single source of truth for the
 * token-saving knobs a board client (the L2LR skill / MCP client) obeys.
 *
 * The stored shape on the project is small: {"profile": "...", "overrides": {...}}.
 * The *effective* config is computed as DEFAULTS ← profile preset ← overrides, so
 * a project with no config behaves exactly like the pre-config API (the DEFAULTS
 * reproduce the historical response shape — nothing changes until someone opts in).
 *
 * Two classes of settings:
 *  - server-enforced: the API simply returns cheaper payloads (board.*, task.fields,
 *    claim.return_details, response.errors). The client never needs to read them.
 *  - client-hint (see CLIENT_HINT_KEYS): change how the *client* behaves (execution
 *    mode, re-read policy, …). Surfaced to the client as a delta-vs-defaults block.
 */
class ProjectConfig
{
    /**
     * The effective defaults == the historical API behaviour. Absent config ⇒
     * these ⇒ byte-compatible responses for existing consumers.
     *
     * @var array<string, string|bool|int>
     */
    public const DEFAULTS = [
        // Board output (server-enforced)
        'board.scope' => 'pickable',   // next_only | pickable | all
        'board.format' => 'full',      // terse | lean | full
        'board.aggregates' => true,    // include totals + per-phase aggregates
        'board.diff_mode' => 'off',    // etag (304 on match) | off

        // Task details (server-enforced)
        'task.fields' => 'full',       // minimal | standard | full
        'claim.return_details' => true,

        // Round-trips & actions (server-enforced)
        'actions.bundling' => false,   // advertise the bundled /complete action
        'response.errors' => 'standard', // minimal | standard | verbose

        // Client-hints (change client behaviour)
        'reread.policy' => 'before_every_action', // on_conflict | once_per_pick | before_every_action
        'instructions.delivery' => 'full_doc',     // server_enforced | changelog | full_doc
        'conventions.delivery' => 'full_prose',    // server_enforced | snippet | full_prose
        'execution.mode' => 'single_session',      // headless | subagent | single_session
        'context.between_tasks' => 'continue',     // stop | continue
        'parallelism.max_workers' => 1,
        'concerns.attitude' => 'ausgewogen',       // kritisch | ausgewogen | mutig
    ];

    /**
     * Allowed values per enum key (bool/int keys are validated separately).
     *
     * @var array<string, array<int, string>>
     */
    public const OPTIONS = [
        'board.scope' => ['next_only', 'pickable', 'all'],
        'board.format' => ['terse', 'lean', 'full'],
        'board.diff_mode' => ['etag', 'off'],
        'task.fields' => ['minimal', 'standard', 'full'],
        'response.errors' => ['minimal', 'standard', 'verbose'],
        'reread.policy' => ['on_conflict', 'once_per_pick', 'before_every_action'],
        'instructions.delivery' => ['server_enforced', 'changelog', 'full_doc'],
        'conventions.delivery' => ['server_enforced', 'snippet', 'full_prose'],
        'execution.mode' => ['headless', 'subagent', 'single_session'],
        'context.between_tasks' => ['stop', 'continue'],
        'concerns.attitude' => ['kritisch', 'ausgewogen', 'mutig'],
    ];

    /** @var array<int, string> boolean-valued keys */
    public const BOOL_KEYS = ['board.aggregates', 'claim.return_details', 'actions.bundling'];

    /** @var array<int, string> integer-valued keys (min 1) */
    public const INT_KEYS = ['parallelism.max_workers'];

    /**
     * Keys surfaced to the client as behaviour hints (only these ride along in the
     * board's client_hints block, and only when they differ from the defaults).
     *
     * @var array<int, string>
     */
    public const CLIENT_HINT_KEYS = [
        'reread.policy',
        'actions.bundling',
        'execution.mode',
        'context.between_tasks',
        'parallelism.max_workers',
        'instructions.delivery',
        'conventions.delivery',
        'concerns.attitude',
    ];

    /**
     * The profile used when a project has no explicit profile set — Claude's
     * recommended balance. New/unconfigured projects behave like this.
     */
    public const DEFAULT_PROFILE = 'recommended';

    /**
     * Named presets bundling the knobs. Only the keys that differ from DEFAULTS
     * are listed; the rest fall through to DEFAULTS.
     *
     * @var array<string, array<string, string|bool|int>>
     */
    public const PROFILES = [
        // Claude's recommendation and the default: every risk-free saving on
        // (ETag, bundling, server-enforced rules, minimal acks), but responses
        // stay structured (lean JSON, standard fields) and errors keep their
        // messages. Isolated context per task (subagent) — the big lever —
        // without requiring a headless CLI setup.
        'recommended' => [
            'board.format' => 'lean',
            'board.aggregates' => false,
            'board.diff_mode' => 'etag',
            'task.fields' => 'standard',
            'claim.return_details' => false,
            'actions.bundling' => true,
            'reread.policy' => 'once_per_pick',
            'instructions.delivery' => 'server_enforced',
            'conventions.delivery' => 'server_enforced',
            'execution.mode' => 'subagent',
            'context.between_tasks' => 'stop',
            'parallelism.max_workers' => 2,
        ],
        // Lowest footprint: one pick, terse text, server enforces the rest.
        'economy' => [
            'board.scope' => 'next_only',
            'board.format' => 'terse',
            'board.aggregates' => false,
            'board.diff_mode' => 'etag',
            'task.fields' => 'minimal',
            'claim.return_details' => false,
            'actions.bundling' => true,
            'response.errors' => 'minimal',
            'reread.policy' => 'on_conflict',
            'instructions.delivery' => 'server_enforced',
            'conventions.delivery' => 'server_enforced',
            'execution.mode' => 'headless',
            'context.between_tasks' => 'stop',
            'parallelism.max_workers' => 4,
        ],
        // Middle ground: full pickable list but lean, deltas over full docs.
        'balanced' => [
            'board.scope' => 'pickable',
            'board.format' => 'lean',
            'board.aggregates' => false,
            'board.diff_mode' => 'etag',
            'task.fields' => 'standard',
            'claim.return_details' => true,
            'actions.bundling' => true,
            'response.errors' => 'standard',
            'reread.policy' => 'once_per_pick',
            'instructions.delivery' => 'changelog',
            'conventions.delivery' => 'snippet',
            'execution.mode' => 'subagent',
            'context.between_tasks' => 'stop',
            'parallelism.max_workers' => 2,
        ],
        // Everything verbose — for debugging / onboarding. Equals DEFAULTS.
        'rich' => [],
    ];

    /**
     * Resolve the effective config for a stored value ({"profile","overrides"}).
     *
     * @param  array<string, mixed>|null  $stored
     * @return array<string, string|bool|int>
     */
    public static function effective(?array $stored): array
    {
        $profile = $stored['profile'] ?? null;
        if (! is_string($profile) || $profile === '') {
            $profile = self::DEFAULT_PROFILE;
        }
        $preset = self::PROFILES[$profile] ?? [];
        $overrides = is_array($stored['overrides'] ?? null) ? $stored['overrides'] : [];

        // Only known keys survive, in the DEFAULTS order.
        $merged = array_merge(self::DEFAULTS, $preset, array_intersect_key($overrides, self::DEFAULTS));

        return array_replace(self::DEFAULTS, array_intersect_key($merged, self::DEFAULTS));
    }

    /**
     * The client-hint delta: hint keys whose effective value differs from the
     * default. Empty when the client should just use its baked-in defaults
     * (⇒ no client_hints block ⇒ zero extra tokens).
     *
     * @param  array<string, string|bool|int>  $effective
     * @return array<string, string|bool|int>
     */
    public static function clientHints(array $effective): array
    {
        $hints = [];
        foreach (self::CLIENT_HINT_KEYS as $key) {
            if (($effective[$key] ?? null) !== self::DEFAULTS[$key]) {
                $hints[$key] = $effective[$key];
            }
        }

        return $hints;
    }

    /**
     * Validate and clean an override map. Keys are validated explicitly (they
     * contain dots — e.g. "board.scope" — so Laravel's dot-path rules can't
     * address them). Throws a 422 for unknown keys or out-of-range values.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, string|bool|int>
     */
    public static function validateOverrides(array $overrides): array
    {
        $clean = [];
        $errors = [];

        foreach ($overrides as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                $errors["overrides.$key"] = "Unbekannter Konfigurationsschlüssel: \"{$key}\".";
            } elseif (in_array($key, self::BOOL_KEYS, true)) {
                $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($bool === null) {
                    $errors["overrides.$key"] = "\"{$key}\" erwartet einen Wahrheitswert.";
                } else {
                    $clean[$key] = $bool;
                }
            } elseif (in_array($key, self::INT_KEYS, true)) {
                if (! is_numeric($value) || (int) $value < 1 || (int) $value > 32) {
                    $errors["overrides.$key"] = "\"{$key}\" erwartet eine Zahl zwischen 1 und 32.";
                } else {
                    $clean[$key] = (int) $value;
                }
            } elseif (! in_array($value, self::OPTIONS[$key] ?? [], true)) {
                $allowed = implode(', ', self::OPTIONS[$key] ?? []);
                $errors["overrides.$key"] = "\"{$key}\" muss einer von: {$allowed}.";
            } else {
                $clean[$key] = (string) $value;
            }
        }

        if ($errors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        return $clean;
    }
}
