<?php

use App\Models\User;

test('muting a user records the mute without touching the follow graph', function () {
    $muter = User::factory()->create();
    $muted = User::factory()->create();

    $muter->following()->attach($muted->id);

    $muter->mute($muted);

    expect($muter->hasMuted($muted))->toBeTrue();
    expect($muted->mutedBy()->whereKey($muter->id)->exists())->toBeTrue();
    // Unlike block(), mute() has no reason to touch who follows whom.
    expect($muter->isFollowing($muted))->toBeTrue();
});

test('a user cannot mute themselves', function () {
    $user = User::factory()->create();

    expect(fn () => $user->mute($user))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('unmuting removes the mute record', function () {
    $muter = User::factory()->create();
    $muted = User::factory()->create();

    $muter->mute($muted);
    $muter->unmute($muted);

    expect($muter->hasMuted($muted))->toBeFalse();
});

test('muting the same person twice does not create a duplicate row', function () {
    $muter = User::factory()->create();
    $muted = User::factory()->create();

    $muter->mute($muted);
    $muter->mute($muted);

    expect($muter->muting()->wherePivot('muted_id', $muted->id)->count())->toBe(1);
});
