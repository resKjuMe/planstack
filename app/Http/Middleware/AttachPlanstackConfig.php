<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the per-project board config once per request and makes it available
 * to resources/controllers via request attributes, then stamps every API
 * response with X-Planstack-Config-Version so a client can cheaply detect drift
 * (one integer header, no extra round-trip).
 *
 * Runs after SubstituteBindings, so the {project} route parameter is already a
 * resolved Project model.
 */
class AttachPlanstackConfig
{
    public const CONFIG_ATTR = 'planstack.config';

    public const VERSION_HEADER = 'X-Planstack-Config-Version';

    /** The header a client sends with the config version it currently knows. */
    public const CLIENT_VERSION_HEADER = 'X-Planstack-Client-Config-Version';

    /** Global revision of the shared skill content (manual + rules); drift ⇒ re-fetch. */
    public const SKILL_REVISION_HEADER = 'X-Planstack-Skill-Revision';

    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');

        if ($project instanceof Project) {
            $request->attributes->set(self::CONFIG_ATTR, $project->effectiveConfig());
        }

        $response = $next($request);

        if ($project instanceof Project) {
            $response->headers->set(self::VERSION_HEADER, (string) $project->config_version);
            $response->headers->set(self::SKILL_REVISION_HEADER, \App\Support\SkillTemplate::sharedRevision());
        }

        return $response;
    }

    /**
     * Read a resolved config value from the current request (falls back to the
     * shipped default when the middleware did not run, e.g. non-project routes).
     */
    public static function value(Request $request, string $key): string|bool|int
    {
        $config = $request->attributes->get(self::CONFIG_ATTR);

        return is_array($config) && array_key_exists($key, $config)
            ? $config[$key]
            : \App\Support\ProjectConfig::DEFAULTS[$key];
    }
}
