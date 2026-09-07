<?php

use App\Models\Heritage;
use App\Models\User;
use Livewire\Livewire;

test('adding a custom heritage requires a region', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.roots')
        ->set('newHeritageName', 'Yoruba')
        ->call('addHeritage')
        ->assertHasErrors('newHeritageRegion');

    expect(Heritage::where('slug', 'yoruba')->exists())->toBeFalse();
});

test('adding a custom heritage stores it with the chosen region', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::settings.roots')
        ->set('newHeritageName', 'Yoruba')
        ->set('newHeritageRegion', 'Africa')
        ->call('addHeritage')
        ->assertHasNoErrors();

    $heritage = Heritage::where('slug', 'yoruba')->first();

    expect($heritage)->not->toBeNull();
    expect($heritage->region)->toBe('Africa');
    expect($component->get('heritageIds'))->toContain($heritage->id);
});

test('adding an existing regionless heritage backfills its region', function () {
    $heritage = Heritage::create(['name' => 'Yoruba', 'slug' => 'yoruba']);
    expect($heritage->region)->toBeNull();

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.roots')
        ->set('newHeritageName', 'Yoruba')
        ->set('newHeritageRegion', 'Africa')
        ->call('addHeritage')
        ->assertHasNoErrors();

    expect($heritage->fresh()->region)->toBe('Africa');
});
