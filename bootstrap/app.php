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
        // Runs after SubstituteBindings so {project} is a resolved model. Resolves
        // the per-project board config and stamps X-Planstack-Config-Version on
        // every API response (drift detection without an extra round-trip).
        $middleware->appendToGroup('api', \App\Http\Middleware\AttachPlanstackConfig::class);

        // Applies the authenticated user's stored locale to every web request.
        // Appended so it runs after the auth middleware and $request->user() is set.
        $middleware->appendToGroup('web', \App\Http\Middleware\SetLocale::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
