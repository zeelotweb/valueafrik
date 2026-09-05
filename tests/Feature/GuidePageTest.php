<?php

use App\Models\User;

test('the guide page lists all six pillars for a guest and points them to registration', function () {
    $this->get(route('guide'))
        ->assertOk()
        ->assertSee('Identity & Profiles')
        ->assertSee('Cultural Spotlights & Bridge Posts')
        ->assertSee('Culture Circles')
        ->assertSee('Bridge Score & Badges')
        ->assertSee('Discovery & Matchmaking')
        ->assertSee('Live & Video')
        ->assertSeeInOrder(['Join Free', 'Ready to build your first bridge?'])
        ->assertSeeHtml(route('register'));
});

test('the guide page sends a logged-in user to feature pages instead of registration', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('guide'))
        ->assertOk()
        ->assertSeeHtml(route('discover.index'))
        ->assertSeeHtml(route('communities.index'))
        ->assertSeeHtml(route('live.index'))
        ->assertDontSee('Ready to build your first bridge?');
});
