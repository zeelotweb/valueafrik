<?php

use App\Models\SocialAccount;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeGoogleUser(string $id = 'google-123', string $email = 'newcomer@example.com'): SocialiteUser
{
    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = $id;
    $socialiteUser->email = $email;
    $socialiteUser->name = 'Newcomer';
    $socialiteUser->nickname = 'newcomer';
    $socialiteUser->avatar = 'https://example.com/avatar.png';
    $socialiteUser->token = 'access-token';
    $socialiteUser->refreshToken = 'refresh-token';

    return $socialiteUser;
}

test('a new google sign-in creates a user and social account, and regenerates the session', function () {
    Socialite::shouldReceive('driver->user')->andReturn(fakeGoogleUser());

    $this->get('/');
    $sessionIdBefore = session()->getId();

    $this->get(route('social.callback', 'google'))
        ->assertRedirect(route('dashboard', absolute: false));

    expect(session()->getId())->not->toBe($sessionIdBefore);

    $user = User::where('email', 'newcomer@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->email_verified_at)->not->toBeNull();

    expect(SocialAccount::where('provider', 'google')->where('provider_id', 'google-123')->exists())->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('signing in again with the same google account logs into the existing user without duplicating it', function () {
    Socialite::shouldReceive('driver->user')->andReturn(fakeGoogleUser());
    $this->get(route('social.callback', 'google'));
    $firstUserId = auth()->id();
    auth()->logout();

    Socialite::shouldReceive('driver->user')->andReturn(fakeGoogleUser());
    $this->get(route('social.callback', 'google'));

    expect(User::where('email', 'newcomer@example.com')->count())->toBe(1);
    $this->assertAuthenticatedAs(User::find($firstUserId));
});

test('a returning google account created before the verification fix is self-healed on next login', function () {
    $unverified = User::factory()->create(['email' => 'newcomer@example.com', 'email_verified_at' => null]);
    SocialAccount::create([
        'user_id' => $unverified->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
    ]);

    Socialite::shouldReceive('driver->user')->andReturn(fakeGoogleUser());
    $this->get(route('social.callback', 'google'));

    expect($unverified->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('a google user signed in this way can call another user without being blocked from their own call room', function () {
    Socialite::shouldReceive('driver->user')->andReturn(fakeGoogleUser());
    $this->get(route('social.callback', 'google'));
    $googleUser = auth()->user();

    $invitee = User::factory()->create(['last_seen_at' => now()]);

    $session = \App\Models\LiveSession::startCallWith($googleUser, $invitee);

    $this->get(route('live.show', $session))->assertOk();
});
