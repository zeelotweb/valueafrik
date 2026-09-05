<?php

use App\Models\Community;
use App\Models\User;
use Livewire\Livewire;

test('discover search finds a user by name regardless of follow state', function () {
    $viewer = User::factory()->create();
    $match = User::factory()->create(['name' => 'Amara Osei']);
    $noise = User::factory()->create(['name' => 'Someone Else']);

    Livewire::actingAs($viewer)
        ->test('pages::discover.index')
        ->set('search', 'Amara')
        ->assertSee('Amara Osei')
        ->assertDontSee('Someone Else');
});

test('discover search reaches users excluded from the curated sections, but clearing it hides them again', function () {
    $viewer = User::factory()->create();
    $followed = User::factory()->create(['name' => 'Already Followed Match']);
    $viewer->following()->attach($followed->id);

    Livewire::actingAs($viewer)
        ->test('pages::discover.index')
        ->set('search', 'Already Followed')
        ->assertSee('Already Followed Match')
        ->set('search', '')
        ->assertDontSee('Already Followed Match');
});

test('communities search filters by name', function () {
    $owner = User::factory()->create();

    $match = $owner->ownedCommunities()->create([
        'name' => 'Diaspora Chefs',
        'slug' => 'diaspora-chefs',
        'visibility' => Community::VISIBILITY_PUBLIC,
        'participation_level' => Community::PARTICIPATION_POST,
    ]);
    $match->members()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

    $noise = $owner->ownedCommunities()->create([
        'name' => 'Book Club',
        'slug' => 'book-club',
        'visibility' => Community::VISIBILITY_PUBLIC,
        'participation_level' => Community::PARTICIPATION_POST,
    ]);
    $noise->members()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::communities.index')
        ->set('search', 'Diaspora')
        ->assertSee('Diaspora Chefs')
        ->assertDontSee('Book Club');
});

test('communities search shows a not-found message rather than an empty list', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::communities.index')
        ->set('search', 'Nothing Matches This')
        ->assertSee('No communities match');
});
