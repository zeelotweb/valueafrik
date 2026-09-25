<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    /** Show the "Coming soon" page instead of a feature that is switched off. */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (config("features.{$feature}")) {
            return $next($request);
        }

        return response()->view('coming-soon');
    }
}
