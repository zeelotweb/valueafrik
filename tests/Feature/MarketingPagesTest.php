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
        ->assertSee('Six pillars, built in order.')
        ->assertSee('Identity & Profiles')
        ->assertSee('Live & Video');
});

test('the guide link is reachable from the welcome page footer', function () {
    $this->get(route('home'))->assertOk();

    $this->get(route('guide'))->assertOk();
});
