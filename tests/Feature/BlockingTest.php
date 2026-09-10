<?php

use App\Models\Conversation;
use App\Models\CultureSprintPool;
use App\Models\User;
use Livewire\Livewire;

test('blocking a user records the block and severs any existing follow both ways', function () {
    $blocker = User::factory()->create();
    $blocked = User::factory()->create();

    $blocker->following()->attach($blocked->id);
    $blocked->following()->attach($blocker->id);

    $blocker->block($blocked);

    expect($blocker->hasBlocked($blocked))->toBeTrue();
    expect($blocked->isBlockedBy($blocker))->toBeTrue();
    expect($blocker->isFollowing($blocked))->toBeFalse();
    expect($blocked->isFollowing($blocker))->toBeFalse();
});

test('a user cannot block themselves', function () {
    $user = User::factory()->create();

    expect(fn () => $user->block($user))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('unblocking removes the block record', function () {
    $blocker = User::factory()->create();
    $blocked = User::factory()->create();

    $blocker->block($blocked);
    $blocker->unblock($blocked);

    expect($blocker->hasBlocked($blocked))->toBeFalse();
});

test('block button toggles block state and redirects', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::profile.block-button', ['user' => $target])
        ->assertSet('isBlocked', false)
        ->call('toggle')
        ->assertRedirect(route('profile.show', $target));

    expect($viewer->fresh()->hasBlocked($target))->toBeTrue();

    Livewire::actingAs($viewer)
        ->test('pages::profile.block-button', ['user' => $target])
        ->assertSet('isBlocked', true)
        ->call('toggle');

    expect($viewer->fresh()->hasBlocked($target))->toBeFalse();
});

test('a blocked relationship prevents following in either direction', function () {
    $blocker = User::factory()->create();
    $blocked = User::factory()->create();
    $blocker->block($blocked);

    Livewire::actingAs($blocked)
        ->test('pages::profile.follow-button', ['user' => $blocker])
        ->call('toggle')
        ->assertForbidden();

    Livewire::actingAs($blocker)
        ->test('pages::profile.follow-button', ['user' => $blocked])
        ->call('toggle')
        ->assertForbidden();
});

test('a blocked relationship prevents starting a new conversation in either direction', function () {
    $blocker = User::factory()->create();
    $blocked = User::factory()->create();
    $blocker->block($blocked);

    Livewire::actingAs($blocked)
        ->test('pages::profile.message-button', ['user' => $blocker])
        ->call('startConversation')
        ->assertForbidden();

    Livewire::actingAs($blocker)
        ->test('pages::profile.message-button', ['user' => $blocked])
        ->call('startConversation')
        ->assertForbidden();
});

test('sending a message into an existing conversation is blocked once either side blocks the other', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);

    $b->block($a);

    Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->assertSet('canMessage', false)
        ->set('body', 'hello')
        ->call('send')
        ->assertForbidden();
});

test('culture sprint matching excludes a blocked pair in either direction', function () {
    $me = User::factory()->create(['last_seen_at' => now()]);
    $blockedCandidate = User::factory()->create(['last_seen_at' => now()]);
    $me->block($blockedCandidate);

    CultureSprintPool::create(['user_id' => $blockedCandidate->id, 'topic' => 'Food']);

    expect(CultureSprintPool::findWaitingPartnerFor($me, 'Food', null))->toBeNull();

    // The reverse direction — the candidate blocked me — must also be excluded.
    $me->unblock($blockedCandidate);
    $blockedCandidate->block($me);

    expect(CultureSprintPool::findWaitingPartnerFor($me, 'Food', null))->toBeNull();
});

test('settings blocked users page lists and unblocks people', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create(['name' => 'Blocked Person']);
    $viewer->block($target);

    Livewire::actingAs($viewer)
        ->test('pages::settings.blocked')
        ->assertSee('Blocked Person')
        ->call('unblock', $target->id);

    expect($viewer->fresh()->hasBlocked($target))->toBeFalse();
});
