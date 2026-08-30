<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('user can upload an avatar', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('profile.avatar'), [
            'photo' => UploadedFile::fake()->image('avatar.jpg', 400, 400),
        ]);

    $response->assertOk()->assertJsonStructure(['url']);

    $path = $user->profile->fresh()->avatar_path;

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

test('user can upload a cover photo', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('profile.cover'), [
            'photo' => UploadedFile::fake()->image('cover.jpg', 1600, 500),
        ]);

    $response->assertOk()->assertJsonStructure(['url']);

    $path = $user->profile->fresh()->cover_path;

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

test('re-uploading an avatar deletes the previous file', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('profile.avatar'), [
        'photo' => UploadedFile::fake()->image('first.jpg'),
    ]);

    $firstPath = $user->profile->fresh()->avatar_path;

    $this->actingAs($user)->postJson(route('profile.avatar'), [
        'photo' => UploadedFile::fake()->image('second.jpg'),
    ]);

    $secondPath = $user->profile->fresh()->avatar_path;

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);
});

test('non-image files are rejected', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('profile.avatar'), [
            'photo' => UploadedFile::fake()->create('document.pdf', 100),
        ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['photo']);
});

test('oversized avatars are rejected', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('profile.avatar'), [
            'photo' => UploadedFile::fake()->image('big.jpg', 100, 100)->size(6000),
        ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['photo']);
});

test('guests cannot upload a profile photo', function () {
    $response = $this->postJson(route('profile.avatar'), [
        'photo' => UploadedFile::fake()->image('avatar.jpg'),
    ]);

    $response->assertUnauthorized();
});
