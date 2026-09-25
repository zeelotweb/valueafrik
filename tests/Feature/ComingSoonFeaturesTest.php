<?php

use App\Models\LiveSession;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    config(['features.live' => false]);
});

test('live, calls and culture sprint show a coming soon page', function () {
    $user = User::factory()->create();

    foreach (['live.index', 'culture-sprint.index'] as $route) {
        $this->actingAs($user)->get(route($route))->assertOk()->assertSee('This is coming soon');
    }
});

test('the guide and roadmap still explain live video but mark it coming soon', function () {
    $this->get(route('guide'))->assertOk()->assertSee('Live &amp; Video', false)->assertSee('Coming soon');
    $this->get(route('roadmap'))->assertOk()->assertSee('Live &amp; Video', false)->assertSee('Coming soon');
    $this->get(route('home'))->assertOk()->assertSee('Coming soon');
});

test('the terms stay available and say live video and calls are not yet available', function () {
    $this->get(route('legal.terms'))->assertOk()->assertSee('currently disabled')->assertDontSee('This is coming soon');
});

test('links to switched-off features are replaced by a coming soon badge', function () {
    $this->get(route('home'))
        ->assertSee('Coming soon')
        ->assertDontSee('href="'.route('live.index').'"', false);

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
    config(['features.live' => true]);

    $this->get(route('guide'))->assertOk()->assertDontSee('Coming soon');
    $this->get(route('legal.terms'))->assertOk()->assertDontSee('This is coming soon');
});
