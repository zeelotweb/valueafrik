<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catches every path into the app — password registration, Google, and
 * Facebook alike — at one place instead of needing each login/register
 * success handler to know about onboarding.
 */
class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Guarded on verification too: an unverified user can still reach a
        // few "auth"-only routes (settings/profile) before Fortify's own
        // verified-email gate applies elsewhere — sending them to onboarding
        // here, before they're verified, would bounce them straight back to
        // the verification notice and loop.
        if (
            $user
            && $user->hasVerifiedEmail()
            && ! $user->hasCompletedOnboarding()
            && ! $request->routeIs('onboarding.index')
        ) {
            return redirect()->route('onboarding.index');
        }

        return $next($request);
    }
}
