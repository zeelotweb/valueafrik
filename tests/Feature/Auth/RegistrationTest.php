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

test('nobody but the reserved brand email can register as valueafrik', function () {
    $this->post('/register', [
        'name' => 'valueAFRIK',
        'email' => 'impersonator@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('name');

    expect(User::where('email', 'impersonator@example.com')->exists())->toBeFalse();
});

test('the reserved brand email can register as valueafrik', function () {
    $this->post('/register', [
        'name' => 'valueAFRIK',
        'email' => 'valueafrik@yahoo.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $brand = User::where('email', 'valueafrik@yahoo.com')->first();

    expect($brand)->not->toBeNull();
    expect($brand->name)->toBe('valueAFRIK');
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
