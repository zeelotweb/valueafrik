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
        ->assertSee("You're all caught up.");
});
