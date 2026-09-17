<?php

use App\Models\User;

test('the very first person to register becomes the platform admin automatically', function () {
    $this->post('/register', [
        'name' => 'Founding User',
        'email' => 'founder@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $founder = User::where('email', 'founder@example.com')->firstOrFail();

    expect($founder->isAdmin())->toBeTrue();
});

test('everyone who registers after the first person is a normal, non-admin user', function () {
    User::factory()->create(); // an existing user, so the next signup isn't "first"

    $this->post('/register', [
        'name' => 'Second User',
        'email' => 'second@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $second = User::where('email', 'second@example.com')->firstOrFail();

    expect($second->isAdmin())->toBeFalse();
});
