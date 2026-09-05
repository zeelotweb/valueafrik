<?php

use App\Models\Community;
use App\Models\User;
use Livewire\Livewire;

test('the dashboard shows bridge score, community, and unread counts', function () {
    $user = User::factory()->create();
    $user->awardBridgeScore('wall_post');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Bridge Score')
        ->assertSee((string) $user->bridgeScore())
        ->assertSee('Communities')
        ->assertSee('Messages');
});

test('the dashboard nudges a user with incomplete roots', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSee("You haven't finished your Roots yet");
});

test('a community owner sees a pending join request on the dashboard and can approve it', function () {
    $owner = User::factory()->create();
    $community = $owner->ownedCommunities()->create([
        'name' => 'Needs Review',
        'slug' => 'needs-review',
        'visibility' => Community::VISIBILITY_PRIVATE,
        'participation_level' => Community::PARTICIPATION_POST,
    ]);
    $community->members()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

    $requester = User::factory()->create();
    $community->members()->attach($requester->id, ['role' => 'member', 'status' => 'pending']);

    Livewire::actingAs($owner)
        ->test('pages::dashboard.pending-requests')
        ->assertSee('Needs Review')
        ->assertSee($requester->name)
        ->call('approve', $community->id, $requester->id);

    expect($community->fresh()->isMember($requester))->toBeTrue();
});

test('a non moderator sees no pending requests on the dashboard', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.pending-requests')
        ->assertSee('No pending community requests.');
});

test('the communities widget lists communities the user has joined', function () {
    $user = User::factory()->create();
    $community = $user->ownedCommunities()->create([
        'name' => 'My Circle',
        'slug' => 'my-circle',
        'visibility' => Community::VISIBILITY_PUBLIC,
        'participation_level' => Community::PARTICIPATION_POST,
    ]);
    $community->members()->attach($user->id, ['role' => 'owner', 'status' => 'active']);

    Livewire::actingAs($user)
        ->test('pages::dashboard.communities-widget', ['user' => $user])
        ->assertSee('My Circle')
        ->assertDontSee("You haven't joined a community yet");
});

test('the communities widget suggests active public communities when the user has joined none', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();
    $community = $owner->ownedCommunities()->create([
        'name' => 'Open To All',
        'slug' => 'open-to-all',
        'visibility' => Community::VISIBILITY_PUBLIC,
        'participation_level' => Community::PARTICIPATION_POST,
    ]);
    $community->members()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

    $private = $owner->ownedCommunities()->create([
        'name' => 'Hidden Circle',
        'slug' => 'hidden-circle',
        'visibility' => Community::VISIBILITY_PRIVATE,
        'participation_level' => Community::PARTICIPATION_POST,
    ]);
    $private->members()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

    Livewire::actingAs($user)
        ->test('pages::dashboard.communities-widget', ['user' => $user])
        ->assertSee("You haven't joined a community yet")
        ->assertSee('Open To All')
        ->assertDontSee('Hidden Circle');
});

test('the people widget suggests users the viewer does not already follow', function () {
    $viewer = User::factory()->create();
    $followed = User::factory()->create(['name' => 'Already Followed']);
    $stranger = User::factory()->create(['name' => 'Not Yet Followed']);
    $viewer->following()->attach($followed->id);

    Livewire::actingAs($viewer)
        ->test('pages::dashboard.people-widget')
        ->assertSee('Not Yet Followed')
        ->assertDontSee('Already Followed');
});

test('the live now widget shows an active stream', function () {
    $host = User::factory()->create();
    $session = \App\Models\LiveSession::startStream($host, 'Cooking Live');

    Livewire::actingAs(User::factory()->create())
        ->test('pages::dashboard.live-now')
        ->assertSee('Cooking Live')
        ->assertSee($host->name);

    $session->update(['status' => \App\Models\LiveSession::STATUS_ENDED]);
});

test('the live now widget offers to start a stream when no one is live', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::dashboard.live-now')
        ->assertSee('No one is streaming right now.')
        ->assertSee('Start a stream');
});

test('the dashboard activity feed only shows bridge posts with both sides written', function () {
    $initiator = User::factory()->create();
    $partner = User::factory()->create();

    $complete = \App\Models\BridgePost::create([
        'theme' => 'Weddings',
        'initiator_id' => $initiator->id,
        'partner_id' => $partner->id,
        'status' => \App\Models\BridgePost::STATUS_ACTIVE,
        'initiator_body' => 'Our side of it.',
        'partner_body' => 'Our side of it too.',
    ]);

    $incomplete = \App\Models\BridgePost::create([
        'theme' => 'Festivals',
        'initiator_id' => $initiator->id,
        'partner_id' => $partner->id,
        'status' => \App\Models\BridgePost::STATUS_ACTIVE,
        'initiator_body' => 'Only one side so far.',
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::dashboard.activity')
        ->assertSee('Weddings')
        ->assertDontSee('Festivals');
});
