<?php

use App\Models\LiveSession;
use App\Models\User;
use App\Notifications\LiveCallStarted;
use App\Services\LiveKitToken;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('starting a call from a profile creates a live session, redirects, and notifies the other person', function () {
    Notification::fake();

    $host = User::factory()->create();
    $invitee = User::factory()->create();

    Livewire::actingAs($host)
        ->test('pages::profile.start-call-button', ['user' => $invitee])
        ->call('startCall')
        ->assertRedirect();

    $session = LiveSession::first();

    expect($session->host_id)->toBe($host->id);
    expect($session->type)->toBe(LiveSession::TYPE_CALL);
    expect($session->status)->toBe(LiveSession::STATUS_LIVE);
    expect($session->room_name)->not->toBeEmpty();

    Notification::assertSentTo($invitee, LiveCallStarted::class);
});

test('a user cannot start a call with themselves', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::profile.start-call-button', ['user' => $user])
        ->call('startCall')
        ->assertForbidden();

    expect(LiveSession::count())->toBe(0);
});

test('starting a call from a conversation notifies the other participant', function () {
    Notification::fake();

    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = App\Models\Conversation::between($a, $b);

    Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->call('startCall')
        ->assertRedirect();

    Notification::assertSentTo($b, LiveCallStarted::class);
});

test('starting a stream from the dashboard creates a live session of type stream', function () {
    $host = User::factory()->create();

    Livewire::actingAs($host)
        ->test('pages::dashboard.start-stream')
        ->call('startStream')
        ->assertRedirect();

    expect(LiveSession::first()->type)->toBe(LiveSession::TYPE_STREAM);
});

test('the live discovery page only lists currently live streams, not calls or ended sessions', function () {
    $streamHost = User::factory()->create(['name' => 'Streaming Host']);
    $callHost = User::factory()->create(['name' => 'Calling Host']);
    $endedHost = User::factory()->create(['name' => 'Ended Host']);

    LiveSession::create([
        'host_id' => $streamHost->id,
        'room_name' => 'live-stream',
        'type' => LiveSession::TYPE_STREAM,
        'status' => LiveSession::STATUS_LIVE,
        'started_at' => now(),
    ]);
    LiveSession::create([
        'host_id' => $callHost->id,
        'room_name' => 'live-call',
        'type' => LiveSession::TYPE_CALL,
        'status' => LiveSession::STATUS_LIVE,
        'started_at' => now(),
    ]);
    LiveSession::create([
        'host_id' => $endedHost->id,
        'room_name' => 'ended-stream',
        'type' => LiveSession::TYPE_STREAM,
        'status' => LiveSession::STATUS_ENDED,
        'started_at' => now(),
        'ended_at' => now(),
    ]);

    $viewer = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::live.index')
        ->assertSee('Streaming Host')
        ->assertDontSee('Calling Host')
        ->assertDontSee('Ended Host');
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
    config(['services.livekit.api_key' => null, 'services.livekit.api_secret' => null, 'services.livekit.url' => null]);

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
        'services.livekit.url' => 'wss://example.livekit.cloud',
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
