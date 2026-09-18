<?php

use App\Models\Hashtag;
use App\Models\User;
use App\Models\WallPost;
use App\Support\RichText;
use Livewire\Livewire;

// --- WallPost::canView() ----------------------------------------------------

test('a public wall post can be viewed by anyone', function () {
    $author = User::factory()->create();
    $stranger = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'hi', 'visibility' => WallPost::VISIBILITY_PUBLIC]);

    expect($post->canView($stranger))->toBeTrue();
    expect($post->canView($author))->toBeTrue();
});

test('a followers-only wall post is only viewable by followers and the author', function () {
    $author = User::factory()->create();
    $follower = User::factory()->create();
    $stranger = User::factory()->create();
    $follower->following()->attach($author->id);

    $post = $author->wallPosts()->create(['body' => 'hi', 'visibility' => WallPost::VISIBILITY_FOLLOWERS_ONLY]);

    expect($post->canView($follower))->toBeTrue();
    expect($post->canView($author))->toBeTrue();
    expect($post->canView($stranger))->toBeFalse();
});

test('a private wall post is only viewable by the author', function () {
    $author = User::factory()->create();
    $follower = User::factory()->create();
    $follower->following()->attach($author->id);

    $post = $author->wallPosts()->create(['body' => 'hi', 'visibility' => WallPost::VISIBILITY_PRIVATE]);

    expect($post->canView($author))->toBeTrue();
    expect($post->canView($follower))->toBeFalse();
});

// --- Profile wall listing ----------------------------------------------------

test('a stranger visiting a profile only sees that profile\'s public wall posts', function () {
    $author = User::factory()->create();
    $author->wallPosts()->create(['body' => 'public one', 'visibility' => WallPost::VISIBILITY_PUBLIC]);
    $author->wallPosts()->create(['body' => 'followers one', 'visibility' => WallPost::VISIBILITY_FOLLOWERS_ONLY]);
    $author->wallPosts()->create(['body' => 'private one', 'visibility' => WallPost::VISIBILITY_PRIVATE]);

    $stranger = User::factory()->create();

    Livewire::actingAs($stranger)
        ->test('pages::profile.wall-posts', ['user' => $author])
        ->assertSee('public one')
        ->assertDontSee('followers one')
        ->assertDontSee('private one');
});

test('a follower visiting a profile sees public and followers-only posts, not private ones', function () {
    $author = User::factory()->create();
    $author->wallPosts()->create(['body' => 'public one', 'visibility' => WallPost::VISIBILITY_PUBLIC]);
    $author->wallPosts()->create(['body' => 'followers one', 'visibility' => WallPost::VISIBILITY_FOLLOWERS_ONLY]);
    $author->wallPosts()->create(['body' => 'private one', 'visibility' => WallPost::VISIBILITY_PRIVATE]);

    $follower = User::factory()->create();
    $follower->following()->attach($author->id);

    Livewire::actingAs($follower)
        ->test('pages::profile.wall-posts', ['user' => $author])
        ->assertSee('public one')
        ->assertSee('followers one')
        ->assertDontSee('private one');
});

test('visiting your own wall shows every post regardless of visibility', function () {
    $author = User::factory()->create();
    $author->wallPosts()->create(['body' => 'public one', 'visibility' => WallPost::VISIBILITY_PUBLIC]);
    $author->wallPosts()->create(['body' => 'followers one', 'visibility' => WallPost::VISIBILITY_FOLLOWERS_ONLY]);
    $author->wallPosts()->create(['body' => 'private one', 'visibility' => WallPost::VISIBILITY_PRIVATE]);

    Livewire::actingAs($author)
        ->test('pages::profile.wall-posts', ['user' => $author])
        ->assertSee('public one')
        ->assertSee('followers one')
        ->assertSee('private one');
});

// --- Dashboard following-activity -------------------------------------------

test('the following-activity feed excludes a private wall post but includes a followers-only one', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $viewer->following()->attach($author->id);

    $author->wallPosts()->create(['body' => 'followers one', 'visibility' => WallPost::VISIBILITY_FOLLOWERS_ONLY]);
    $author->wallPosts()->create(['body' => 'private one', 'visibility' => WallPost::VISIBILITY_PRIVATE]);

    Livewire::actingAs($viewer)
        ->test('pages::dashboard.following-activity')
        ->assertSee('followers one')
        ->assertDontSee('private one');
});

// --- Topics/hashtag page ------------------------------------------------------

test('the topics page never leaks a private or followers-only wall post to someone who cannot view it', function () {
    $author = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'secret #leaktag content', 'visibility' => WallPost::VISIBILITY_PRIVATE]);
    RichText::syncHashtags($post, $post->body);

    $hashtag = Hashtag::where('name', 'leaktag')->firstOrFail();
    $stranger = User::factory()->create();

    Livewire::actingAs($stranger)
        ->test('pages::topics.show', ['hashtag' => $hashtag])
        ->assertDontSee('secret');

    Livewire::actingAs($author)
        ->test('pages::topics.show', ['hashtag' => $hashtag])
        ->assertSee('secret');
});

// --- Bookmarks -----------------------------------------------------------------

test('a bookmarked followers-only wall post disappears from bookmarks once the bookmarker unfollows', function () {
    $author = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'members only content', 'visibility' => WallPost::VISIBILITY_FOLLOWERS_ONLY]);

    $follower = User::factory()->create();
    $follower->following()->attach($author->id);
    $post->bookmarks()->create(['user_id' => $follower->id]);

    Livewire::actingAs($follower)
        ->test('pages::bookmarks.index')
        ->assertSee('members only content');

    $follower->following()->detach($author->id);

    Livewire::actingAs($follower)
        ->test('pages::bookmarks.index')
        ->assertDontSee('members only content');
});
