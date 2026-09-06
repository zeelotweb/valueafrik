<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cheap heartbeat-based presence: touch last_seen_at on authenticated
 * requests, throttled so we're not writing on every single request (a
 * Livewire-heavy page fires many). This is what call ringing checks
 * against to decide whether the other person is online at all.
 */
class TrackLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && (! $user->last_seen_at || $user->last_seen_at->lt(now()->subSeconds(30)))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
