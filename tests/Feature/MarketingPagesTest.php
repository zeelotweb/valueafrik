<?php

test('the welcome page no longer lists the six pillars but links to the guide', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Six pillars, built in order.')
        ->assertSee('Guide');
});

test('the guide page lists all six pillars', function () {
    $this->get(route('guide'))
        ->assertOk()
        ->assertSee('Six pillars. One idea.')
        ->assertSee('Identity & Profiles')
        ->assertSee('Live & Video');
});

test('the guide link is reachable from the welcome page footer', function () {
    $this->get(route('home'))->assertOk();

    $this->get(route('guide'))->assertOk();
});

test('the roadmap page explains all six pillars mechanically, distinct from the guide', function () {
    $this->get(route('roadmap'))
        ->assertOk()
        ->assertSee('Roadmap')
        ->assertSee('Not a pitch')
        ->assertSee('Identity & Profiles')
        ->assertSee('Bridge Posts')
        ->assertSee('Culture Circles')
        ->assertSee('Bridge Score & Badges')
        ->assertSee('Discovery & Matchmaking')
        ->assertSee('Live & Video')
        // The real, current point values and badge thresholds, not
        // hardcoded copy — this stays accurate if config/bridge_score.php
        // ever changes.
        ->assertSee('+'.config('bridge_score.points.reaction_given'))
        ->assertSee(config('bridge_score.badges.500.name'));
});

test('the roadmap link is reachable from the footer, alongside guide', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeHtml(route('roadmap'))
        ->assertSeeHtml(route('guide'));

    $this->get(route('roadmap'))->assertOk();
});
