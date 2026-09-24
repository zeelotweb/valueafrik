<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Language order: the signed-in user's saved choice, then the choice this
     * browser made, then the browser's own Accept-Language, then the default.
     */
    public function handle(Request $request, Closure $next): Response
    {
        static::apply($request);

        return $next($request);
    }

    /** Also used for error pages, which can render before the web middleware (and the session) ran. */
    public static function apply(Request $request): void
    {
        $available = array_keys(config('locales.available'));

        try {
            $saved = $request->user()?->locale;
        } catch (\Throwable) {
            $saved = null;
        }

        $locale = collect([
            $saved,
            $request->hasSession() ? $request->session()->get('locale') : null,
            $request->getPreferredLanguage($available),
        ])->first(fn ($candidate) => is_string($candidate) && in_array($candidate, $available, true))
            ?? config('locales.default');

        app()->setLocale($locale);
        Carbon::setLocale($locale);
        Date::setLocale($locale);
        Number::useLocale($locale);
    }
}
