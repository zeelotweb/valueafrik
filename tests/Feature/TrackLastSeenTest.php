<?php

use App\Models\User;

test('an authenticated request stamps last_seen_at when it is stale', function () {
    $user = User::factory()->create(['last_seen_at' => now()->subMinutes(5)]);

    $this->actingAs($user)->get(route('dashboard'));

    expect($user->fresh()->last_seen_at->diffInSeconds(now()))->toBeLessThan(5);
});

test('a request does not rewrite last_seen_at when it is already fresh', function () {
    $fresh = now()->subSeconds(5);
    $user = User::factory()->create(['last_seen_at' => $fresh]);

    $this->actingAs($user)->get(route('dashboard'));

    expect($user->fresh()->last_seen_at->timestamp)->toBe($fresh->timestamp);
});

test('a guest request does not error and touches nothing', function () {
    $this->get(route('home'))->assertOk();
});
