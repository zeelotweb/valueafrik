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
        ->call('openModal')
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

test('a user can reply to a comment and the reply shows up scoped to that comment only', function () {
    $author = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Hello wall.']);
    $commenter = User::factory()->create();
    $comment = $post->comments()->create(['user_id' => $commenter->id, 'body' => 'Nice post.']);

    $replier = User::factory()->create();

    $component = Livewire::actingAs($replier)
        ->test('pages::shared.comments', ['commentable' => $post])
        ->call('openReplies', $comment->id)
        ->assertSet('viewingReplyFor', $comment->id)
        ->set('replyBody', 'Totally agree!')
        ->call('postReply')
        ->assertSee('Totally agree!');

    expect($comment->replies()->count())->toBe(1);
    expect($comment->replies()->first()->body)->toBe('Totally agree!');
    expect($replier->fresh()->bridgeScore())->toBe(2);

    // The reply is attributed to the same post (for the total count) but
    // must not leak into the top-level comments list.
    $component->call('openModal');
    expect($component->get('comments')->pluck('id')->all())->toBe([$comment->id]);

    // The post's total comment count includes replies.
    expect($post->fresh()->commentsCount())->toBe(2);
});

test('a user can edit and delete their own reply but not someone elses', function () {
    $author = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Hello wall.']);
    $comment = $post->comments()->create(['user_id' => $author->id, 'body' => 'Top level.']);
    $replier = User::factory()->create();
    $reply = $post->comments()->create(['user_id' => $replier->id, 'parent_id' => $comment->id, 'body' => 'A reply.']);

    $intruder = User::factory()->create();

    Livewire::actingAs($intruder)
        ->test('pages::shared.comments', ['commentable' => $post])
        ->call('startEdit', $reply->id)
        ->assertForbidden();

    Livewire::actingAs($replier)
        ->test('pages::shared.comments', ['commentable' => $post])
        ->call('openReplies', $comment->id)
        ->call('startEdit', $reply->id)
        ->set('editBody', 'Edited reply.')
        ->call('update')
        ->assertSee('Edited reply.');

    expect($reply->fresh()->body)->toBe('Edited reply.');
    expect($reply->fresh()->edited_at)->not->toBeNull();

    Livewire::actingAs($replier)
        ->test('pages::shared.comments', ['commentable' => $post])
        ->call('delete', $reply->id);

    expect($comment->replies()->count())->toBe(0);
});

test('deleting a comment cascades to delete its replies', function () {
    $author = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Hello wall.']);
    $comment = $post->comments()->create(['user_id' => $author->id, 'body' => 'Top level.']);
    $post->comments()->create(['user_id' => $author->id, 'parent_id' => $comment->id, 'body' => 'A reply.']);

    Livewire::actingAs($author)
        ->test('pages::shared.comments', ['commentable' => $post])
        ->call('delete', $comment->id);

    expect(\App\Models\Comment::whereKey($comment->id)->exists())->toBeFalse();
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
        ->call('openModal')
        ->set('body', 'Glad to be here.')
        ->call('post')
        ->assertSee('Glad to be here.');

    expect($post->fresh()->reactionsCount())->toBe(1);
    expect($post->fresh()->commentsCount())->toBe(1);
});

test('a user can bookmark a wall post and unbookmark it, with no bridge score awarded', function () {
    $author = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Save me for later.']);
    $viewer = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::shared.bookmark', ['bookmarkable' => $post])
        ->call('toggle')
        ->assertSet('bookmarked', true);

    expect($post->fresh()->bookmarks()->count())->toBe(1);
    expect($viewer->fresh()->bridgeScore())->toBe(0);

    Livewire::actingAs($viewer)
        ->test('pages::shared.bookmark', ['bookmarkable' => $post])
        ->call('toggle')
        ->assertSet('bookmarked', false);

    expect($post->fresh()->bookmarks()->count())->toBe(0);
});

test('bookmarked posts appear on the bookmarks page and stay private to the bookmarker', function () {
    $author = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'A post worth saving.']);

    $bookmarker = User::factory()->create();
    $post->bookmarks()->create(['user_id' => $bookmarker->id]);

    Livewire::actingAs($bookmarker)
        ->test('pages::bookmarks.index')
        ->assertSee('A post worth saving.');

    $other = User::factory()->create();

    Livewire::actingAs($other)
        ->test('pages::bookmarks.index')
        ->assertDontSee('A post worth saving.')
        ->assertSee("You haven't bookmarked anything yet.");
});

test('picking an emoji reaction replaces a heart, and picking the heart replaces an emoji', function () {
    $author = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Hello wall.']);
    $viewer = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::shared.reactions', ['reactable' => $post])
        ->call('toggle')
        ->assertSet('myType', Reaction::TYPE_LIKE);

    Livewire::actingAs($viewer)
        ->test('pages::shared.emoji-reactions', ['reactable' => $post])
        ->call('react', '🔥')
        ->assertSet('myEmoji', '🔥')
        ->assertSet('count', 1);

    expect($post->fresh()->reactionsCount())->toBe(1);
    expect($post->fresh()->myReactionType($viewer))->toBe('🔥');

    Livewire::actingAs($viewer)
        ->test('pages::shared.reactions', ['reactable' => $post])
        ->call('toggle')
        ->assertSet('myType', Reaction::TYPE_LIKE);

    expect($post->fresh()->reactionsCount())->toBe(1);
    expect($post->fresh()->myReactionType($viewer))->toBe(Reaction::TYPE_LIKE);
});

test('picking the same emoji again removes the reaction', function () {
    $author = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Hello wall.']);
    $viewer = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::shared.emoji-reactions', ['reactable' => $post])
        ->call('react', '😂')
        ->assertSet('myEmoji', '😂')
        ->call('react', '😂')
        ->assertSet('myEmoji', null);

    expect($post->fresh()->reactionsCount())->toBe(0);
});

test('an unlisted emoji is rejected', function () {
    $author = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Hello wall.']);
    $viewer = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::shared.emoji-reactions', ['reactable' => $post])
        ->call('react', '💀')
        ->assertStatus(422);
});

test('a user can upvote a comment, and upvoting again removes it', function () {
    $author = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Hello wall.']);
    $comment = $post->comments()->create(['user_id' => $author->id, 'body' => 'Top level.']);
    $voter = User::factory()->create();

    Livewire::actingAs($voter)
        ->test('pages::shared.comments', ['commentable' => $post])
        ->call('openModal')
        ->call('voteUp', $comment->id);

    expect($comment->fresh()->upvotesCount())->toBe(1);
    expect($comment->fresh()->downvotesCount())->toBe(0);
    expect($comment->fresh()->myVote($voter))->toBe(\App\Models\CommentVote::TYPE_UP);

    Livewire::actingAs($voter)
        ->test('pages::shared.comments', ['commentable' => $post])
        ->call('openModal')
        ->call('voteUp', $comment->id);

    expect($comment->fresh()->upvotesCount())->toBe(0);
    expect($comment->fresh()->myVote($voter))->toBeNull();
});

test('downvoting a comment a user already upvoted switches the vote instead of stacking', function () {
    $author = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Hello wall.']);
    $comment = $post->comments()->create(['user_id' => $author->id, 'body' => 'Top level.']);
    $voter = User::factory()->create();

    Livewire::actingAs($voter)
        ->test('pages::shared.comments', ['commentable' => $post])
        ->call('openModal')
        ->call('voteUp', $comment->id)
        ->call('voteDown', $comment->id);

    expect($comment->fresh()->upvotesCount())->toBe(0);
    expect($comment->fresh()->downvotesCount())->toBe(1);
    expect($comment->fresh()->myVote($voter))->toBe(\App\Models\CommentVote::TYPE_DOWN);
    expect(\App\Models\CommentVote::where('comment_id', $comment->id)->count())->toBe(1);
});

test('a reply can be voted on independently of its parent comment', function () {
    $author = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Hello wall.']);
    $comment = $post->comments()->create(['user_id' => $author->id, 'body' => 'Top level.']);
    $reply = $post->comments()->create(['user_id' => $author->id, 'parent_id' => $comment->id, 'body' => 'A reply.']);
    $voter = User::factory()->create();

    Livewire::actingAs($voter)
        ->test('pages::shared.comments', ['commentable' => $post])
        ->call('openReplies', $comment->id)
        ->call('voteUp', $reply->id);

    expect($reply->fresh()->upvotesCount())->toBe(1);
    expect($comment->fresh()->upvotesCount())->toBe(0);
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
