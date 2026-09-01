<?php

use App\Models\Heritage;
use App\Models\Interest;
use App\Models\User;
use Livewire\Livewire;

test('a user with no roots filled in still sees new members', function () {
    $viewer = User::factory()->create(['name' => 'Viewer Person']);
    $other = User::factory()->create(['name' => 'Someone Else']);

    Livewire::actingAs($viewer)
        ->test('pages::discover.index')
        ->assertSee('Someone Else')
        ->assertSee('New here')
        ->assertDontSee('Viewer Person');
});

test('someone sharing an interest appears under shares your curiosities', function () {
    $viewer = User::factory()->create();
    $match = User::factory()->create(['name' => 'Curious Match']);
    $interest = Interest::create(['name' => 'Music & Fusion', 'slug' => 'music-fusion']);
    $viewer->interests()->attach($interest->id);
    $match->interests()->attach($interest->id);

    Livewire::actingAs($viewer)
        ->test('pages::discover.index')
        ->assertSee('Shares your curiosities')
        ->assertSee('Curious Match')
        ->assertSee('1 shared interest');
});

test('someone with no overlapping interests does not appear in the curiosities section', function () {
    $viewer = User::factory()->create();
    $stranger = User::factory()->create(['name' => 'No Overlap']);
    $mine = Interest::create(['name' => 'Music & Fusion', 'slug' => 'music-fusion']);
    $theirs = Interest::create(['name' => 'Food & Cuisine', 'slug' => 'food-cuisine']);
    $viewer->interests()->attach($mine->id);
    $stranger->interests()->attach($theirs->id);

    Livewire::actingAs($viewer)
        ->test('pages::discover.index')
        ->assertDontSee('Shares your curiosities');
});

test('someone with a different heritage appears under a different perspective', function () {
    $viewer = User::factory()->create();
    $bridge = User::factory()->create(['name' => 'Bridge Candidate']);
    $mine = Heritage::create(['name' => 'Yoruba', 'slug' => 'yoruba']);
    $theirs = Heritage::create(['name' => 'Irish', 'slug' => 'irish']);
    $viewer->heritages()->attach($mine->id);
    $bridge->heritages()->attach($theirs->id);

    Livewire::actingAs($viewer)
        ->test('pages::discover.index')
        ->assertSee('A different perspective')
        ->assertSee('Bridge Candidate');
});

test('someone with the same heritage does not appear in the different perspective section', function () {
    $viewer = User::factory()->create();
    $sameHeritage = User::factory()->create(['name' => 'Same Roots']);
    $heritage = Heritage::create(['name' => 'Yoruba', 'slug' => 'yoruba']);
    $viewer->heritages()->attach($heritage->id);
    $sameHeritage->heritages()->attach($heritage->id);

    Livewire::actingAs($viewer)
        ->test('pages::discover.index')
        ->assertDontSee('A different perspective');
});

test('a user already followed is excluded from every section', function () {
    $viewer = User::factory()->create();
    $followed = User::factory()->create(['name' => 'Already Following']);
    $viewer->following()->attach($followed->id);

    Livewire::actingAs($viewer)
        ->test('pages::discover.index')
        ->assertDontSee('Already Following');
});

test('the follow button on a discover card actually follows', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::profile.follow-button', ['user' => $target])
        ->call('toggle');

    expect($viewer->fresh()->isFollowing($target))->toBeTrue();
});

test('a second follow button for the same person stays in sync after another instance toggles', function () {
    // Discover can show the same person in two sections at once (e.g. both
    // "a different perspective" and "new here"), each with its own follow
    // button instance. Toggling one shouldn't leave the other showing stale
    // state until a full page reload.
    $viewer = User::factory()->create();
    $target = User::factory()->create();

    $cardOne = Livewire::actingAs($viewer)->test('pages::profile.follow-button', ['user' => $target]);
    $cardTwo = Livewire::actingAs($viewer)->test('pages::profile.follow-button', ['user' => $target]);

    $cardOne->call('toggle');
    $cardTwo->dispatch('follow-toggled');

    expect($cardTwo->get('isFollowing'))->toBeTrue();
});
