<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied platform-wide (see bootstrap/app.php) rather than only on
 * specific routes — a ban should end the session immediately, on
 * whatever page the person happens to be on next, not just block a
 * handful of gated routes.
 */
class EnsureNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->isBanned()) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', __('Your account has been suspended.'));
        }

        return $next($request);
    }
}
