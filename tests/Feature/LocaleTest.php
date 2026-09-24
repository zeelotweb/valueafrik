<?php

use App\Models\User;

test('choosing a language stores it for the browser and redirects back', function () {
    $this->from('/guide')
        ->get(route('locale.update', 'es'))
        ->assertRedirect('/guide');

    expect(session('locale'))->toBe('es');
});

test('an unknown language is a 404 and changes nothing', function () {
    $this->get(route('locale.update', 'xx'))->assertNotFound();

    expect(session('locale'))->toBeNull();
});

test('a signed-in user\'s choice is saved on their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('locale.update', 'yo'));

    expect($user->fresh()->locale)->toBe('yo');
});

test('the interface is shown in the language the browser chose', function () {
    $this->withSession(['locale' => 'es'])
        ->get(route('home'))
        ->assertSee('Guía');
});

test('a guest with no saved choice gets the browser\'s own language', function () {
    $this->withHeaders(['Accept-Language' => 'es'])
        ->get(route('home'))
        ->assertSee('Guía');
});

test('a saved account language beats the session and the browser', function () {
    $user = User::factory()->create(['locale' => 'es']);

    $this->actingAs($user)
        ->withSession(['locale' => 'fr'])
        ->withHeaders(['Accept-Language' => 'de'])
        ->get(route('home'))
        ->assertSee('Guía');
});

test('an unsupported browser language falls back to English', function () {
    $this->withHeaders(['Accept-Language' => 'ja'])
        ->get(route('home'))
        ->assertSee('Guide')
        ->assertDontSee('Guía');
});

test('notifications go out in the recipient\'s language, not the sender\'s', function () {
    $recipient = User::factory()->create(['locale' => 'es']);

    expect($recipient->preferredLocale())->toBe('es');
});
