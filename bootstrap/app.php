<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'hq' => \App\Http\Middleware\RequireHeadOffice::class,
            'api.client' => \App\Http\Middleware\AuthenticateApiClient::class,
        ]);
        // Multi-SACCO: choose the database before the session starts
        $middleware->web(prepend: [\App\Http\Middleware\IdentifyTenant::class]);
        $middleware->api(prepend: [\App\Http\Middleware\IdentifyTenant::class]);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        // Behind a load balancer / Cloudflare: TRUSTED_PROXIES=* in .env
        if (env('TRUSTED_PROXIES')) $middleware->trustProxies(at: env('TRUSTED_PROXIES') === '*' ? '*' : explode(',', env('TRUSTED_PROXIES')));
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Friendly alerts instead of stack traces (full details still go to storage/logs/laravel.log)
        \App\Exceptions\FriendlyErrors::register($exceptions);
    })->create();
