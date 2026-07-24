<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum-Stateful-Auth für gleich-Origin-Aufrufe aus dem Browser: erlaubt
        // dem React-Board, /api-Routen (z. B. GET /api/projects/{alias}) mit der
        // bestehenden Web-Session/Cookie statt einem Bearer-Token aufzurufen.
        // Muss der Group vorangestellt werden, damit Session/Cookies vor der
        // Auth laufen. Bearer-Token-Clients (Agenten/CLI/MCP) bleiben unberührt.
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Runs after SubstituteBindings so {project} is a resolved model. Resolves
        // the per-project board config and stamps X-Planstack-Config-Version on
        // every API response (drift detection without an extra round-trip).
        $middleware->appendToGroup('api', \App\Http\Middleware\AttachPlanstackConfig::class);

        // Applies the authenticated user's stored locale to every web request.
        // Appended so it runs after the auth middleware and $request->user() is set.
        // Also on the api group so same-origin browser fetches (shared React store,
        // e.g. GET /api/status-config) return locale-aware labels; bearer-token
        // clients (CLI/MCP) have no session user here and keep the default locale.
        $middleware->appendToGroup('web', \App\Http\Middleware\SetLocale::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\SetLocale::class);

        // „Inertia über Blade": HandleInertiaRequests richtet Inertia ein (Root-
        // View, Shared-Data, Versions-/Redirect-Handling); BladeToInertia läuft
        // darin und verwandelt die Blade-Seiten-Hülle (PageEnvelope) in eine
        // Inertia-Antwort. Reihenfolge: erst Inertia-Setup (außen), dann die
        // Umwandlung (innen), damit deren Antwort noch das Inertia-Handling durchläuft.
        $middleware->appendToGroup('web', \App\Http\Middleware\HandleInertiaRequests::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\BladeToInertia::class);

        // GitHub sendet keinen CSRF-Token; der Webhook-Empfang muss davon
        // ausgenommen werden (Authentizität via HMAC-Signatur, siehe Controller).
        $middleware->validateCsrfTokens(except: ['hooks/git']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
