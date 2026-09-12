<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify's password.email and password.update routes carry no throttle
 * middleware and no configurable limiter — unlike login and two-factor,
 * there's no `fortify.limiters` key for them at all — so without this,
 * /forgot-password can be hammered indefinitely (mail queue exhaustion,
 * email enumeration by timing). Since those routes are registered by the
 * package, not this app, the check happens here in the 'web' group rather
 * than as route-level middleware.
 */
class ThrottlePasswordResetRequests
{
    private const ROUTE_NAMES = ['password.email', 'password.update'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->route()?->getName(), self::ROUTE_NAMES, true)) {
            return $next($request);
        }

        return app(ThrottleRequests::class)->handle($request, $next, 'password-reset');
    }
}
