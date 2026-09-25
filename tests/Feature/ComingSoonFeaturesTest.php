<?php

use App\Models\LiveSession;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    config(['features.live' => false, 'features.guide' => false, 'features.terms' => false]);
});

test('live, calls and culture sprint show a coming soon page', function () {
    $user = User::factory()->create();

    foreach (['live.index', 'culture-sprint.index'] as $route) {
        $this->actingAs($user)->get(route($route))->assertOk()->assertSee('This is coming soon');
    }
});

test('the guide and terms show a coming soon page', function () {
    $this->get(route('guide'))->assertOk()->assertSee('This is coming soon');
    $this->get(route('legal.terms'))->assertOk()->assertSee('This is coming soon');
});

test('links to switched-off features are replaced by a coming soon badge', function () {
    $this->get(route('home'))
        ->assertSee('Coming soon')
        ->assertDontSee('href="'.route('guide').'"', false)
        ->assertDontSee('href="'.route('live.index').'"', false)
        ->assertDontSee('href="'.route('legal.terms').'"', false);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSee('data-test="coming-soon-live"', false)
        ->assertDontSee('data-test="start-stream-button"', false);
});

test('a call or stream cannot be started while live is switched off', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    expect(fn () => LiveSession::startCallWith($a, $b))->toThrow(NotFoundHttpException::class);
    expect(fn () => LiveSession::startStream($a))->toThrow(NotFoundHttpException::class);
});

test('everything is available again once the features are switched on', function () {
    config(['features.live' => true, 'features.guide' => true, 'features.terms' => true]);

    $this->get(route('guide'))->assertOk()->assertDontSee('This is coming soon');
    $this->get(route('legal.terms'))->assertOk()->assertDontSee('This is coming soon');
});
