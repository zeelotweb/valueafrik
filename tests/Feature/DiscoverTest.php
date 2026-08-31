<?php

use App\Models\Heritage;
use App\Models\User;
use Livewire\Livewire;

test('discover lists other users but not the viewer themselves', function () {
    $viewer = User::factory()->create(['name' => 'Viewer Person']);
    $other = User::factory()->create(['name' => 'Someone Else']);

    Livewire::actingAs($viewer)
        ->test('pages::discover.index')
        ->assertSee('Someone Else')
        ->assertDontSee('Viewer Person');
});

test('discover shows a users heritage when set', function () {
    $viewer = User::factory()->create();
    $other = User::factory()->create();
    $heritage = Heritage::create(['name' => 'Yoruba', 'slug' => 'yoruba']);
    $other->heritages()->attach($heritage->id);

    Livewire::actingAs($viewer)
        ->test('pages::discover.index')
        ->assertSee('Yoruba');
});
