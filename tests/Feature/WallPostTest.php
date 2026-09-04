<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

test('user can post text to their own wall', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::profile.wall-composer')
        ->set('body', 'Hello from the wall.')
        ->call('post')
        ->assertHasNoErrors();

    expect($user->wallPosts()->count())->toBe(1);
    expect($user->wallPosts()->first()->body)->toBe('Hello from the wall.');
});

test('user can attach photos to a wall post', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::profile.wall-composer')
        ->set('body', 'With a photo.')
        ->set('photos', [UploadedFile::fake()->image('photo.jpg')])
        ->call('post')
        ->assertHasNoErrors();

    $post = $user->wallPosts()->first();

    expect($post->media)->toHaveCount(1);
    Storage::disk('public')->assertExists($post->media->first()->path);
});

test('user can remove a staged photo before posting', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::profile.wall-composer')
        ->set('body', 'With two photos, then one.')
        ->set('photos', [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.jpg'),
        ])
        ->call('removePhoto', 0);

    expect($component->get('photos'))->toHaveCount(1);
    expect($component->get('photos')[0]->getClientOriginalName())->toBe('second.jpg');

    $component->call('post')->assertHasNoErrors();

    expect($user->wallPosts()->first()->media)->toHaveCount(1);
});

test('a wall post requires a body or a photo', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::profile.wall-composer')
        ->set('body', '')
        ->call('post')
        ->assertHasErrors(['body']);

    expect($user->wallPosts()->count())->toBe(0);
});

test('wall posts are listed on the profile', function () {
    $user = User::factory()->create();
    $user->wallPosts()->create(['body' => 'A visible post']);

    Livewire::actingAs($user)
        ->test('pages::profile.wall-posts', ['user' => $user])
        ->assertSee('A visible post');
});

test('owner can delete their own wall post and its media', function () {
    $user = User::factory()->create();
    $post = $user->wallPosts()->create(['body' => 'Delete me']);
    $media = $post->media()->create([
        'user_id' => $user->id,
        'disk' => 'public',
        'path' => UploadedFile::fake()->image('photo.jpg')->store('wall-media', 'public'),
        'type' => 'image',
    ]);

    Livewire::actingAs($user)
        ->test('pages::profile.wall-posts', ['user' => $user])
        ->call('delete', $post->id);

    expect($user->wallPosts()->count())->toBe(0);
    Storage::disk('public')->assertMissing($media->path);
});

test('a user cannot delete another users wall post', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $post = $owner->wallPosts()->create(['body' => 'Not yours']);

    Livewire::actingAs($intruder)
        ->test('pages::profile.wall-posts', ['user' => $owner])
        ->call('delete', $post->id)
        ->assertForbidden();

    expect($owner->wallPosts()->count())->toBe(1);
});
