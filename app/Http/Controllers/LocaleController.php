<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('locales.available')), 404);

        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['locale' => $locale])->save();

        // Back to where they were; a full page load so direction and fonts apply everywhere.
        $back = redirect()->back(fallback: route('home'));

        // Only known anchors, so this can't be turned into an arbitrary fragment.
        return $request->query('anchor') === 'site-footer' ? $back->withFragment('site-footer') : $back;
    }
}
