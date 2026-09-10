<?php

use App\Models\Community;
use App\Models\Hashtag;
use App\Models\User;
use App\Notifications\Mentioned;
use App\Support\RichText;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('a username is generated automatically at creation and collisions get a numeric suffix', function () {
    $first = User::factory()->create(['name' => 'Jordan Lee']);
    $second = User::factory()->create(['name' => 'Jordan Lee']);

    expect($first->username)->toBe('jordan-lee');
    expect($second->username)->toBe('jordan-lee-2');
});

test('posting a wall post with a hashtag creates and links the hashtag', function () {
    $author = User::factory()->create();

    Livewire::actingAs($author)
        ->test('pages::profile.wall-composer')
        ->set('body', 'Excited for #CultureSprint this weekend!')
        ->call('post');

    $post = $author->wallPosts()->first();

    expect(Hashtag::where('name', 'culturesprint')->exists())->toBeTrue();
    expect($post->hashtags()->pluck('name')->all())->toBe(['culturesprint']);
});

test('editing a post to remove a hashtag un-links it', function () {
    $author = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Talking about #food today.']);
    RichText::syncHashtags($post, $post->body);

    expect($post->hashtags()->count())->toBe(1);

    Livewire::actingAs($author)
        ->test('pages::profile.wall-composer')
        ->call('loadForEdit', $post->id)
        ->set('body', 'No more tags here.')
        ->call('post');

    expect($post->fresh()->hashtags()->count())->toBe(0);
});

test('mentioning a user in a post notifies them once and links their profile', function () {
    Notification::fake();

    $author = User::factory()->create();
    $mentioned = User::factory()->create(['name' => 'Amara Osei']);

    Livewire::actingAs($author)
        ->test('pages::profile.wall-composer')
        ->set('body', "Great meeting you, @{$mentioned->username}!")
        ->call('post');

    $post = $author->wallPosts()->first();

    expect($post->mentions()->where('user_id', $mentioned->id)->exists())->toBeTrue();
    Notification::assertSentTo($mentioned, Mentioned::class);

    // Re-saving with the same mention (an edit that doesn't change it)
    // must not re-notify.
    Notification::fake();

    Livewire::actingAs($author)
        ->test('pages::profile.wall-composer')
        ->call('loadForEdit', $post->id)
        ->set('body', "Great meeting you, @{$mentioned->username}! Edited.")
        ->call('post');

    Notification::assertNothingSent();
});

test('mentioning yourself does not send a notification', function () {
    Notification::fake();

    $author = User::factory()->create();

    Livewire::actingAs($author)
        ->test('pages::profile.wall-composer')
        ->set('body', "Note to self @{$author->username}.")
        ->call('post');

    Notification::assertNothingSent();

    $post = $author->wallPosts()->first();
    expect($post->mentions()->where('user_id', $author->id)->exists())->toBeTrue();
});

test('a comment can mention a user and links to the post it lives on', function () {
    Notification::fake();

    $postAuthor = User::factory()->create();
    $post = $postAuthor->wallPosts()->create(['body' => 'Hello wall.']);
    $commenter = User::factory()->create();
    $mentioned = User::factory()->create();

    Livewire::actingAs($commenter)
        ->test('pages::shared.comments', ['commentable' => $post])
        ->call('openModal')
        ->set('body', "Hey @{$mentioned->username}, check this out.")
        ->call('post');

    $comment = $post->comments()->first();
    expect($comment->mentions()->where('user_id', $mentioned->id)->exists())->toBeTrue();

    Notification::assertSentTo($mentioned, function (Mentioned $notification) use ($post) {
        return $notification->toArray($notification)['url'] === $post->url();
    });
});

test('RichText renders hashtags and mentions as links using the recorded tags, not arbitrary text', function () {
    $author = User::factory()->create(['name' => 'Test Author']);
    $post = $author->wallPosts()->create(['body' => "Hi @{$author->username}, check #test and #nottagged"]);

    // Only "#test" was ever synced — "#nottagged" was never recorded.
    RichText::syncHashtags($post, "Hi @{$author->username}, check #test");
    RichText::syncMentions($post, "Hi @{$author->username}, check #test", $author);

    $html = (string) RichText::render($post->body, $post->hashtags, $post->mentions);

    expect($html)->toContain('href="'.route('profile.show', $author).'"');
    expect($html)->toContain('href="'.route('topics.show', Hashtag::where('name', 'test')->first()).'"');
    expect($html)->not->toContain('href="'.route('topics.show', ['hashtag' => 'nottagged']).'"');
});

test('the topics page lists posts tagged with that hashtag across wall and community posts', function () {
    $owner = User::factory()->create();
    $community = $owner->ownedCommunities()->create([
        'name' => 'Tag Test Community',
        'slug' => 'tag-test-community-'.uniqid(),
        'visibility' => Community::VISIBILITY_PUBLIC,
        'participation_level' => Community::PARTICIPATION_POST,
    ]);
    $community->members()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

    $wallPost = $owner->wallPosts()->create(['body' => 'A #sharedtopic wall post.']);
    RichText::syncHashtags($wallPost, $wallPost->body);

    $communityPost = $community->posts()->create(['user_id' => $owner->id, 'body' => 'A #sharedtopic community post.']);
    RichText::syncHashtags($communityPost, $communityPost->body);

    $hashtag = Hashtag::where('name', 'sharedtopic')->firstOrFail();

    Livewire::actingAs($owner)
        ->test('pages::topics.show', ['hashtag' => $hashtag])
        ->assertSee('wall post')
        ->assertSee('community post');
});

test('the mention search endpoint matches by username or name', function () {
    $user = User::factory()->create();
    $target = User::factory()->create(['name' => 'Zuri Adeyemi']);

    $response = $this->actingAs($user)->getJson(route('mentions.search', ['q' => 'zuri']));

    $response->assertOk();
    expect(collect($response->json())->pluck('username'))->toContain($target->username);
});
