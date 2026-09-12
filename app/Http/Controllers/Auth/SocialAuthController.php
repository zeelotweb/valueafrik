<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class SocialAuthController extends Controller
{
    /**
     * Providers enabled for "Sign in with..." on this app.
     *
     * @var list<string>
     */
    private const PROVIDERS = ['google', 'facebook'];

    public function redirect(string $provider): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider, Request $request): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);

        $socialiteUser = Socialite::driver($provider)->user();

        $user = $this->findOrCreateUser($provider, $socialiteUser);

        if ($user === null) {
            return redirect()->route('login')->with(
                'status',
                __('An account with that email already exists. Sign in with your password to continue.'),
            );
        }

        Auth::login($user, remember: true);

        // The password-login path (Fortify's PrepareAuthenticatedSession)
        // regenerates the session on every login; this callback skipped
        // that, so an OAuth login kept whatever session ID the browser
        // already had before the Google redirect. Anything that raced with
        // that session — the anonymous session it had a moment earlier, or
        // an old browser tab that never got a fresh cookie — could keep
        // reading the old identity until it happened to touch a fresh page.
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function findOrCreateUser(string $provider, SocialiteUser $socialiteUser): ?User
    {
        $account = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if ($account) {
            $account->update([
                'token' => $socialiteUser->token,
                'refresh_token' => $socialiteUser->refreshToken,
                'avatar' => $socialiteUser->getAvatar(),
            ]);

            // Self-heals any account created before this was fixed — email
            // verification silently never got set (see below), which meant
            // every returning sign-in kept hitting the `verified` middleware.
            if (! $account->user->hasVerifiedEmail()) {
                $account->user->forceFill(['email_verified_at' => now()])->save();
            }

            return $account->user;
        }

        // No SocialAccount is linked to this provider identity yet. If a
        // user with this email already exists (registered by password, or
        // via a different provider), refuse to auto-attach and log in as
        // them — otherwise anyone can pre-register a victim's email with a
        // password, then have the real owner's first Google/Facebook sign-in
        // silently hijacked into the attacker's account. There's no
        // account-linking flow to safely fold the two together, so send
        // them to sign in with their password instead.
        if (User::where('email', $socialiteUser->getEmail())->exists()) {
            return null;
        }

        $user = User::create([
            'email' => $socialiteUser->getEmail(),
            'name' => $socialiteUser->getName() ?: $socialiteUser->getNickname(),
            'password' => Str::password(32),
        ]);

        // email_verified_at isn't mass-assignable (it's not in User::$fillable),
        // so it has to be set explicitly — Google/Facebook already verified
        // this address, there's nothing for us to re-confirm by email.
        if (! $user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $socialiteUser->getId(),
            'avatar' => $socialiteUser->getAvatar(),
            'token' => $socialiteUser->token,
            'refresh_token' => $socialiteUser->refreshToken,
        ]);

        return $user;
    }

    private function ensureProviderIsSupported(string $provider): void
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), Response::HTTP_NOT_FOUND);
    }
}
