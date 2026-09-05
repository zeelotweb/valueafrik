<?php

use App\Models\User;
use Livewire\Livewire;

test('followers tab lists people who follow the profile owner', function () {
    $owner = User::factory()->create();
    $follower = User::factory()->create(['name' => 'Faithful Follower']);
    $follower->following()->attach($owner->id);

    Livewire::actingAs($owner)
        ->test('pages::profile.connections', ['user' => $owner])
        ->assertSet('tab', 'followers')
        ->assertSee('Faithful Follower');
});

test('following tab lists people the profile owner follows', function () {
    $owner = User::factory()->create();
    $followed = User::factory()->create(['name' => 'Followed Person']);
    $owner->following()->attach($followed->id);

    Livewire::actingAs($owner)
        ->test('pages::profile.connections', ['user' => $owner])
        ->call('switchTab', 'following')
        ->assertSee('Followed Person');
});

test('the followers tab label keeps the followers count even while viewing following', function () {
    $owner = User::factory()->create();
    User::factory()->count(4)->create()->each(fn ($user) => $user->following()->attach($owner->id));
    $onlyFollowed = User::factory()->create();
    $owner->following()->attach($onlyFollowed->id);

    Livewire::actingAs($owner)
        ->test('pages::profile.connections', ['user' => $owner])
        ->call('switchTab', 'following')
        ->assertSee('4 followers');
});

test('loadMore increases the page size for the active tab', function () {
    $owner = User::factory()->create();
    User::factory()->count(25)->create()->each(fn ($user) => $user->following()->attach($owner->id));

    $component = Livewire::actingAs($owner)
        ->test('pages::profile.connections', ['user' => $owner]);

    expect($component->get('people')['items'])->toHaveCount(20);
    expect($component->get('people')['hasMore'])->toBeTrue();

    $component->call('loadMore');

    expect($component->get('people')['items'])->toHaveCount(25);
    expect($component->get('people')['hasMore'])->toBeFalse();
});

test('switching tabs resets the page size', function () {
    $owner = User::factory()->create();
    User::factory()->count(25)->create()->each(fn ($user) => $user->following()->attach($owner->id));

    Livewire::actingAs($owner)
        ->test('pages::profile.connections', ['user' => $owner])
        ->call('loadMore')
        ->assertSet('perPage', 40)
        ->call('switchTab', 'following')
        ->assertSet('perPage', 20);
});
