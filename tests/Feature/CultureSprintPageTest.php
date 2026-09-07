<?php

use App\Models\CultureSprintPool;
use App\Models\Heritage;
use App\Models\Language;
use App\Models\LiveSession;
use App\Models\User;
use Livewire\Livewire;

function readyForSprint(string $name = 'Sprinter'): User
{
    $user = User::factory()->create(['name' => $name, 'last_seen_at' => now()]);
    $user->profile()->create(['bio' => 'Here to bridge.']);
    $user->heritages()->attach(Heritage::create(['name' => $name.' Heritage', 'slug' => str($name.'-heritage-'.uniqid())->slug()]));
    $user->languages()->attach(Language::create(['name' => $name.' Language', 'slug' => str($name.'-language-'.uniqid())->slug()]));

    return $user;
}

test('a user with incomplete roots is blocked from joining the pool', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::culture-sprint.index')
        ->call('joinPool')
        ->assertForbidden();

    expect(CultureSprintPool::where('user_id', $user->id)->exists())->toBeFalse();
});

test('joining the pool with nobody else waiting shows the waiting state', function () {
    $user = readyForSprint();

    Livewire::actingAs($user)
        ->test('pages::culture-sprint.index')
        ->set('topic', 'Food')
        ->call('joinPool')
        ->assertSet('waiting', true);

    expect(CultureSprintPool::where('user_id', $user->id)->where('topic', 'Food')->exists())->toBeTrue();
});

test('a topic is required to join the pool', function () {
    $user = readyForSprint();

    Livewire::actingAs($user)
        ->test('pages::culture-sprint.index')
        ->call('joinPool')
        ->assertHasErrors('topic');

    expect(CultureSprintPool::where('user_id', $user->id)->exists())->toBeFalse();
});

test('joining the pool when someone is already waiting on the same topic matches them immediately', function () {
    $waiting = readyForSprint('Waiting Person');
    CultureSprintPool::create(['user_id' => $waiting->id, 'topic' => 'Food']);

    $joiner = readyForSprint('Joining Person');

    Livewire::actingAs($joiner)
        ->test('pages::culture-sprint.index')
        ->set('topic', 'Food')
        ->call('joinPool')
        ->assertSet('waiting', false);

    $session = LiveSession::where('type', LiveSession::TYPE_SPRINT)->first();

    expect($session)->not->toBeNull();
    expect($session->status)->toBe(LiveSession::STATUS_RINGING);
    expect($session->culture_word)->toBe('Food');
    expect(CultureSprintPool::count())->toBe(0);
});

test('leaving the pool removes the pointer row', function () {
    $user = readyForSprint();
    CultureSprintPool::create(['user_id' => $user->id, 'topic' => 'Food']);

    Livewire::actingAs($user)
        ->test('pages::culture-sprint.index')
        ->set('waiting', true)
        ->call('leavePool')
        ->assertSet('waiting', false);

    expect(CultureSprintPool::where('user_id', $user->id)->exists())->toBeFalse();
});

test('accepting a match on the page updates the session and going live issues a token', function () {
    config([
        'services.livekit.api_key' => 'test-key',
        'services.livekit.api_secret' => base64_encode(random_bytes(64)),
        'services.livekit.url' => 'wss://example.livekit.cloud',
    ]);

    $a = readyForSprint('Host Person');
    $b = readyForSprint('Callee Person');
    $session = LiveSession::startSprintMatch($a, $b, 'Food');

    Livewire::actingAs($a)
        ->test('pages::culture-sprint.index')
        ->set('activeSessionId', $session->id)
        ->call('respond', true)
        ->assertSet('session.status', LiveSession::STATUS_RINGING);

    Livewire::actingAs($b)
        ->test('pages::culture-sprint.index')
        ->set('activeSessionId', $session->id)
        ->call('respond', true)
        ->assertSet('session.status', LiveSession::STATUS_LIVE)
        ->assertSet('token', fn ($token) => filled($token));
});

test('completing a sprint from the page ends it', function () {
    $a = readyForSprint('Host Person');
    $b = readyForSprint('Callee Person');
    $session = LiveSession::startSprintMatch($a, $b, 'Food');
    $session->respondToSprint($a, true);
    $session->respondToSprint($b, true);

    Livewire::actingAs($a)
        ->test('pages::culture-sprint.index')
        ->set('activeSessionId', $session->id)
        ->call('complete')
        ->assertSet('session.status', LiveSession::STATUS_ENDED);
});

test('mounting the page picks up a match already in progress after a refresh', function () {
    $a = readyForSprint('Host Person');
    $b = readyForSprint('Callee Person');
    $session = LiveSession::startSprintMatch($a, $b, 'Food');

    Livewire::actingAs($a)
        ->test('pages::culture-sprint.index')
        ->assertSet('activeSessionId', $session->id);
});
