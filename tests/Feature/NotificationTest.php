<?php

use App\Models\BridgePost;
use App\Models\Community;
use App\Models\User;
use App\Notifications\BridgePostAccepted;
use App\Notifications\BridgePostCompleted;
use App\Notifications\BridgePostInvited;
use App\Notifications\CommunityJoinApproved;
use App\Notifications\CommunityJoinRequested;
use App\Notifications\NewFollower;
use App\Notifications\NewMessageReceived;
use App\Notifications\PromotedToMonitor;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function notifiableCommunity(User $owner, array $attributes = []): Community
{
    $community = $owner->ownedCommunities()->create(array_merge([
        'name' => 'Test Community',
        'slug' => 'test-community-'.uniqid(),
        'visibility' => Community::VISIBILITY_PUBLIC,
        'participation_level' => Community::PARTICIPATION_POST,
    ], $attributes));

    $community->members()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

    return $community;
}

test('following someone notifies them', function () {
    Notification::fake();

    $follower = User::factory()->create();
    $followed = User::factory()->create();

    Livewire::actingAs($follower)
        ->test('pages::profile.follow-button', ['user' => $followed])
        ->call('toggle');

    Notification::assertSentTo($followed, NewFollower::class);
    Notification::assertNotSentTo($follower, NewFollower::class);
});

test('a bridge post invite notifies the partner', function () {
    Notification::fake();

    $initiator = User::factory()->create();
    $partner = User::factory()->create(['name' => 'Yuki Tanaka']);

    Livewire::actingAs($initiator)
        ->test('pages::profile.bridge-post-composer')
        ->set('theme', 'Weddings')
        ->call('pickPartner', $partner->id, 'Yuki Tanaka')
        ->call('send');

    Notification::assertSentTo($partner, BridgePostInvited::class);
});

test('accepting a bridge post notifies the initiator', function () {
    Notification::fake();

    $initiator = User::factory()->create();
    $partner = User::factory()->create();
    $bridgePost = BridgePost::create([
        'theme' => 'Weddings',
        'initiator_id' => $initiator->id,
        'partner_id' => $partner->id,
        'status' => BridgePost::STATUS_PENDING,
    ]);

    Livewire::actingAs($partner)
        ->test('pages::dashboard.bridge-post-invites')
        ->call('accept', $bridgePost->id);

    Notification::assertSentTo($initiator, BridgePostAccepted::class);
});

test('completing a bridge post notifies only the other participant', function () {
    Notification::fake();

    $initiator = User::factory()->create();
    $partner = User::factory()->create();
    $bridgePost = BridgePost::create([
        'theme' => 'Weddings',
        'initiator_id' => $initiator->id,
        'partner_id' => $partner->id,
        'status' => BridgePost::STATUS_ACTIVE,
        'partner_body' => 'Already written.',
    ]);

    Livewire::actingAs($initiator)
        ->test('pages::profile.bridge-posts', ['user' => $initiator])
        ->call('startSide', $bridgePost->id)
        ->set('sideBody', 'My side of the story.')
        ->call('submitSide');

    Notification::assertSentTo($partner, BridgePostCompleted::class);
    Notification::assertNotSentTo($initiator, BridgePostCompleted::class);
});

test('requesting to join a private community notifies its moderators', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $community = notifiableCommunity($owner, ['visibility' => Community::VISIBILITY_PRIVATE]);
    $requester = User::factory()->create();

    Livewire::actingAs($requester)
        ->test('pages::communities.join-button', ['community' => $community])
        ->call('join');

    Notification::assertSentTo($owner, CommunityJoinRequested::class);
});

test('approving a join request notifies the requester', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $community = notifiableCommunity($owner, ['visibility' => Community::VISIBILITY_PRIVATE]);
    $requester = User::factory()->create();
    $community->members()->attach($requester->id, ['role' => 'member', 'status' => 'pending']);

    Livewire::actingAs($owner)
        ->test('pages::communities.members', ['community' => $community])
        ->call('approve', $requester->id);

    Notification::assertSentTo($requester, CommunityJoinApproved::class);
});

test('being promoted to monitor sends a notification', function () {
    Notification::fake();
    config(['communities.monitor_milestones' => [0 => 1]]);

    $owner = User::factory()->create();
    $community = notifiableCommunity($owner);
    $member = User::factory()->create();
    $community->members()->attach($member->id, ['role' => 'member', 'status' => 'active']);

    Livewire::actingAs($owner)
        ->test('pages::communities.members', ['community' => $community])
        ->call('promote', $member->id);

    Notification::assertSentTo($member, PromotedToMonitor::class);
});

test('sending a message notifies the other participant', function () {
    Notification::fake();

    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = \App\Models\Conversation::between($a, $b);

    Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->set('body', 'hello')
        ->call('send');

    Notification::assertSentTo($b, NewMessageReceived::class);
});

test('the notifications page lists notifications and marking one read redirects and updates its state', function () {
    $user = User::factory()->create();
    $actor = User::factory()->create(['name' => 'Actor Name']);
    $user->notify(new NewFollower($actor));

    $notification = $user->fresh()->notifications()->first();

    Livewire::actingAs($user)
        ->test('pages::notifications.index')
        ->assertSee('Actor Name')
        ->call('open', $notification->id)
        ->assertRedirect(route('profile.show', $actor));

    expect($user->fresh()->notifications()->first()->read_at)->not->toBeNull();
});

test('mark all as read clears every unread notification', function () {
    $user = User::factory()->create();
    $actor = User::factory()->create();
    $user->notify(new NewFollower($actor));
    $user->notify(new NewFollower($actor));

    expect($user->fresh()->unreadNotifications()->count())->toBe(2);

    Livewire::actingAs($user)
        ->test('pages::notifications.index')
        ->call('markAllRead');

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});
