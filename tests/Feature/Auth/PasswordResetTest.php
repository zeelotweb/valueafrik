<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password reset requests are rate-limited per IP to 5 per minute', function () {
    // Regression guard: Fortify's password.email route carries no throttle
    // middleware and no configurable limiter at all (unlike login and
    // two-factor) — without ThrottlePasswordResetRequests in the 'web'
    // group, this endpoint could be hammered indefinitely. Keyed by IP
    // alone: the password broker already cools down repeats to the same
    // email, so this test uses a different email each request to isolate
    // the IP-scoped gap that cooldown doesn't cover (enumeration/mail-queue
    // flooding by cycling through many candidate emails).
    Notification::fake();

    $emails = User::factory(6)->create()->pluck('email');

    foreach ($emails->take(5) as $email) {
        $this->post(route('password.request'), ['email' => $email])
            ->assertStatus(302);
    }

    $this->post(route('password.request'), ['email' => $emails->last()])
        ->assertStatus(429);
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        return true;
    });
});
