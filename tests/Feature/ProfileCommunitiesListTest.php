<?php

use App\Models\Community;
use App\Models\User;
use Livewire\Livewire;

function communityForList(User $owner, array $attributes = []): Community
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

test('a profile visitor sees the public communities a user belongs to', function () {
    $owner = User::factory()->create();
    $community = communityForList($owner);
    $visitor = User::factory()->create();

    Livewire::actingAs($visitor)
        ->test('pages::profile.communities-list', ['user' => $owner])
        ->assertSee('Test Community')
        ->assertSee('Owner');
});

test('a private community is hidden from other visitors but visible on your own profile', function () {
    $owner = User::factory()->create();
    communityForList($owner, ['visibility' => Community::VISIBILITY_PRIVATE, 'name' => 'Secret Circle', 'slug' => 'secret-circle']);
    $visitor = User::factory()->create();

    Livewire::actingAs($visitor)
        ->test('pages::profile.communities-list', ['user' => $owner])
        ->assertDontSee('Secret Circle');

    Livewire::actingAs($owner)
        ->test('pages::profile.communities-list', ['user' => $owner])
        ->assertSee('Secret Circle');
});

test('a pending private join request is not shown as membership', function () {
    $owner = User::factory()->create();
    $community = communityForList($owner, ['visibility' => Community::VISIBILITY_PRIVATE, 'name' => 'Awaiting Approval', 'slug' => 'awaiting-approval']);
    $requester = User::factory()->create();
    $community->members()->attach($requester->id, ['role' => 'member', 'status' => 'pending']);

    Livewire::actingAs($requester)
        ->test('pages::profile.communities-list', ['user' => $requester])
        ->assertDontSee('Awaiting Approval');
});

test('the empty state differs for your own profile versus someone elses', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();

    Livewire::actingAs($owner)
        ->test('pages::profile.communities-list', ['user' => $owner])
        ->assertSee("You haven't joined any communities yet.");

    Livewire::actingAs($visitor)
        ->test('pages::profile.communities-list', ['user' => $owner])
        ->assertSee("hasn't joined any public communities yet.");
});
