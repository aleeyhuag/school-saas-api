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
        // Trust Render's edge proxy so Laravel knows the original
        // request was HTTPS. Render terminates SSL at their edge and
        // forwards plain HTTP internally to this container — without
        // this, every URL Laravel generates (signed export download
        // links in particular) comes out as http://, which the
        // frontend's HTTPS origin then blocks as mixed content. '*' is
        // the standard/correct setting for a PaaS like Render/Heroku,
        // where the container is never reachable except through their
        // proxy — there's no untrusted network path to worry about.
        $middleware->trustProxies(at: '*');

        // Global safety net — 60 requests/minute per user (or per IP
        // for unauthenticated requests) across the whole API. The
        // auth routes above have their own tighter limits on top of
        // this for the specific endpoints worth throttling harder.
        $middleware->throttleApi();
        $middleware->append(\App\Http\Middleware\AuditApiRequests::class);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'school.active' => \App\Http\Middleware\EnsureSchoolIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
