<?php

use App\Models\LiveSession;
use App\Models\User;
use App\Services\LiveKitToken;
use Livewire\Livewire;

test('starting a call creates a live session and redirects to it', function () {
    $host = User::factory()->create();

    Livewire::actingAs($host)
        ->test('pages::live.index')
        ->call('startCall')
        ->assertRedirect();

    $session = LiveSession::first();

    expect($session->host_id)->toBe($host->id);
    expect($session->type)->toBe(LiveSession::TYPE_CALL);
    expect($session->status)->toBe(LiveSession::STATUS_LIVE);
    expect($session->room_name)->not->toBeEmpty();
});

test('starting a stream creates a live session of type stream', function () {
    $host = User::factory()->create();

    Livewire::actingAs($host)->test('pages::live.index')->call('startStream');

    expect(LiveSession::first()->type)->toBe(LiveSession::TYPE_STREAM);
});

test('anyone can publish in a call but only the host can publish in a stream', function () {
    $host = User::factory()->create();
    $viewer = User::factory()->create();

    $call = LiveSession::create([
        'host_id' => $host->id,
        'room_name' => 'call-1',
        'type' => LiveSession::TYPE_CALL,
        'status' => LiveSession::STATUS_LIVE,
        'started_at' => now(),
    ]);
    $stream = LiveSession::create([
        'host_id' => $host->id,
        'room_name' => 'stream-1',
        'type' => LiveSession::TYPE_STREAM,
        'status' => LiveSession::STATUS_LIVE,
        'started_at' => now(),
    ]);

    expect($call->canPublish($host))->toBeTrue();
    expect($call->canPublish($viewer))->toBeTrue();
    expect($stream->canPublish($host))->toBeTrue();
    expect($stream->canPublish($viewer))->toBeFalse();
});

test('only the host can end a session', function () {
    $host = User::factory()->create();
    $intruder = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id,
        'room_name' => 'room-1',
        'type' => LiveSession::TYPE_CALL,
        'status' => LiveSession::STATUS_LIVE,
        'started_at' => now(),
    ]);

    Livewire::actingAs($intruder)
        ->test('pages::live.show', ['liveSession' => $session])
        ->call('endSession')
        ->assertForbidden();

    expect($session->fresh()->status)->toBe(LiveSession::STATUS_LIVE);

    Livewire::actingAs($host)
        ->test('pages::live.show', ['liveSession' => $session])
        ->call('endSession')
        ->assertRedirect(route('live.index'));

    expect($session->fresh()->status)->toBe(LiveSession::STATUS_ENDED);
    expect($session->fresh()->ended_at)->not->toBeNull();
});

test('the room page shows a not configured message when livekit credentials are missing', function () {
    config(['services.livekit.api_key' => null, 'services.livekit.api_secret' => null, 'services.livekit.host' => null]);

    $host = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id,
        'room_name' => 'room-2',
        'type' => LiveSession::TYPE_CALL,
        'status' => LiveSession::STATUS_LIVE,
        'started_at' => now(),
    ]);

    Livewire::actingAs($host)
        ->test('pages::live.show', ['liveSession' => $session])
        ->assertSee("isn't configured yet");
});

test('a join token generates successfully once livekit credentials are present', function () {
    config([
        'services.livekit.api_key' => 'test-key',
        'services.livekit.api_secret' => base64_encode(random_bytes(64)),
        'services.livekit.host' => 'wss://example.livekit.cloud',
    ]);

    $host = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id,
        'room_name' => 'room-3',
        'type' => LiveSession::TYPE_CALL,
        'status' => LiveSession::STATUS_LIVE,
        'started_at' => now(),
    ]);

    $token = LiveKitToken::generate($session, $host);

    expect($token)->toBeString();
    expect(substr_count($token, '.'))->toBe(2); // a JWT has three dot-separated segments
});

test('an ended session shows nothing to join', function () {
    $host = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id,
        'room_name' => 'room-4',
        'type' => LiveSession::TYPE_CALL,
        'status' => LiveSession::STATUS_ENDED,
        'started_at' => now(),
        'ended_at' => now(),
    ]);

    Livewire::actingAs($host)
        ->test('pages::live.show', ['liveSession' => $session])
        ->assertSee('This session has ended.');
});
