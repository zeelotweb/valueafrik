<?php

use App\Models\User;
use Livewire\Livewire;

test('the header search menu links to discover and communities', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::layout.quick-search')
        ->assertSeeHtml(route('discover.index'))
        ->assertSeeHtml(route('communities.index'));
});
