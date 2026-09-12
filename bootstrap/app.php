<?php

use App\Http\Middleware\EnsureNotBanned;
use App\Http\Middleware\ThrottlePasswordResetRequests;
use App\Http\Middleware\TrackLastSeen;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', EnsureNotBanned::class);
        $middleware->appendToGroup('web', TrackLastSeen::class);
        $middleware->appendToGroup('web', ThrottlePasswordResetRequests::class);

        // Forge puts nginx in front of Octane on the same box (OVH). Trusting
        // only the loopback proxy — not '*' — means we only accept forwarded
        // headers from that local nginx, not from any client claiming to be
        // a proxy. Without this, HTTPS detection and request()->ip() (used
        // by login throttling and bans) see nginx's local connection instead
        // of the real client.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        Integration::handles($exceptions);
    })->create();
