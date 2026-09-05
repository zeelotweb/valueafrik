<?php

use App\Models\Comment;
use App\Models\Community;
use App\Models\Conversation;
use App\Models\Reaction;
use App\Models\User;
use App\Models\WallPost;
use Livewire\Livewire;

test('a user can react to a wall post and react again to remove it', function () {
    $author = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Hello wall.']);

    $viewer = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::shared.reactions', ['reactable' => $post])
        ->call('toggle')
        ->assertSet('reacted', true);

    expect($post->fresh()->reactionsCount())->toBe(1);
    expect($viewer->fresh()->bridgeScore())->toBe(1);

    Livewire::actingAs($viewer)
        ->test('pages::shared.reactions', ['reactable' => $post])
        ->call('toggle')
        ->assertSet('reacted', false);

    expect($post->fresh()->reactionsCount())->toBe(0);
});

test('reacting twice without toggling off does not create duplicate reactions', function () {
    $author = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Hello wall.']);
    $viewer = User::factory()->create();

    $post->reactions()->create(['user_id' => $viewer->id, 'type' => Reaction::TYPE_LIKE]);

    expect(fn () => $post->reactions()->create(['user_id' => $viewer->id, 'type' => Reaction::TYPE_LIKE]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('a user can comment on a wall post and see it appear once the thread is open', function () {
    $author = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Hello wall.']);
    $commenter = User::factory()->create();

    Livewire::actingAs($commenter)
        ->test('pages::shared.comments', ['commentable' => $post])
        ->call('toggle')
        ->set('body', 'Great post!')
        ->call('post')
        ->assertSee('Great post!')
        ->assertSee($commenter->name);

    expect($post->fresh()->commentsCount())->toBe(1);
    expect($commenter->fresh()->bridgeScore())->toBe(2);
});

test('a user can delete their own comment but not someone elses', function () {
    $author = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Hello wall.']);

    $commenter = User::factory()->create();
    $comment = $post->comments()->create(['user_id' => $commenter->id, 'body' => 'Nice.']);

    $intruder = User::factory()->create();

    Livewire::actingAs($intruder)
        ->test('pages::shared.comments', ['commentable' => $post])
        ->call('delete', $comment->id)
        ->assertForbidden();

    expect($post->fresh()->commentsCount())->toBe(1);

    Livewire::actingAs($commenter)
        ->test('pages::shared.comments', ['commentable' => $post])
        ->call('delete', $comment->id);

    expect($post->fresh()->commentsCount())->toBe(0);
});

test('reactions and comments work the same way on a community post', function () {
    $owner = User::factory()->create();
    $community = $owner->ownedCommunities()->create([
        'name' => 'Reaction Circle',
        'slug' => 'reaction-circle-'.uniqid(),
        'visibility' => Community::VISIBILITY_PUBLIC,
        'participation_level' => Community::PARTICIPATION_POST,
    ]);
    $community->members()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

    $post = $community->posts()->create(['user_id' => $owner->id, 'body' => 'Welcome!']);
    $member = User::factory()->create();

    Livewire::actingAs($member)
        ->test('pages::shared.reactions', ['reactable' => $post])
        ->call('toggle')
        ->assertSet('reacted', true);

    Livewire::actingAs($member)
        ->test('pages::shared.comments', ['commentable' => $post])
        ->call('toggle')
        ->set('body', 'Glad to be here.')
        ->call('post')
        ->assertSee('Glad to be here.');

    expect($post->fresh()->reactionsCount())->toBe(1);
    expect($post->fresh()->commentsCount())->toBe(1);
});

test('a message can be reacted to', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);
    $message = $conversation->messages()->create(['user_id' => $a->id, 'body' => 'Hey there.']);

    Livewire::actingAs($b)
        ->test('pages::shared.reactions', ['reactable' => $message])
        ->call('toggle')
        ->assertSet('reacted', true);

    expect($message->fresh()->reactionsCount())->toBe(1);
});
