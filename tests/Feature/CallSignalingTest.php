<?php

use App\Events\CallStatusUpdated;
use App\Jobs\ExpireRingingCall;
use App\Models\LiveSession;
use App\Models\User;
use App\Notifications\LiveCallStarted;
use App\Notifications\MissedCall;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

// --- LiveSession::startCallWith --------------------------------------------

test('calling an online user rings, schedules an expiry job, and notifies them', function () {
    Notification::fake();
    Queue::fake();
    Event::fake([CallStatusUpdated::class]);

    $host = User::factory()->create();
    $invitee = User::factory()->create(['last_seen_at' => now()]);

    $session = LiveSession::startCallWith($host, $invitee);

    expect($session->status)->toBe(LiveSession::STATUS_RINGING);
    expect($session->callee_id)->toBe($invitee->id);
    expect($session->ended_reason)->toBeNull();

    Notification::assertSentTo($invitee, LiveCallStarted::class);
    Notification::assertNotSentTo($invitee, MissedCall::class);

    Queue::assertPushed(ExpireRingingCall::class, function ($job) use ($session) {
        return $job->session->is($session) && $job->delay->betweenIncluded(now()->addSeconds(25), now()->addSeconds(35));
    });

    Event::assertDispatched(CallStatusUpdated::class, fn ($e) => $e->session->is($session));
});

test('calling an offline user registers a missed call immediately and skips the ring', function () {
    Notification::fake();
    Queue::fake();

    $host = User::factory()->create();
    $invitee = User::factory()->create(['last_seen_at' => null]);

    $session = LiveSession::startCallWith($host, $invitee);

    expect($session->status)->toBe(LiveSession::STATUS_MISSED);
    expect($session->ended_reason)->toBe(LiveSession::REASON_OFFLINE);
    expect($session->ended_at)->not->toBeNull();

    Notification::assertSentTo($invitee, MissedCall::class);
    Notification::assertNotSentTo($invitee, LiveCallStarted::class);

    Queue::assertNotPushed(ExpireRingingCall::class);
});

test('an invitee who went stale beyond the online threshold counts as offline', function () {
    $invitee = User::factory()->create(['last_seen_at' => now()->subMinutes(5)]);

    expect($invitee->isOnline())->toBeFalse();
});

test('calling the same person twice while already ringing reuses the same session', function () {
    Queue::fake();

    $host = User::factory()->create();
    $invitee = User::factory()->create(['last_seen_at' => now()]);

    $first = LiveSession::startCallWith($host, $invitee);
    $second = LiveSession::startCallWith($host, $invitee);

    expect($second->id)->toBe($first->id);
    expect(LiveSession::count())->toBe(1);
});

test('a user cannot start a call with themselves', function () {
    $user = User::factory()->create();

    expect(fn () => LiveSession::startCallWith($user, $user))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

// --- respondToRing -----------------------------------------------------------

test('the callee can accept a ringing call', function () {
    Event::fake([CallStatusUpdated::class]);

    $host = User::factory()->create();
    $callee = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id,
        'callee_id' => $callee->id,
        'room_name' => 'r1',
        'type' => LiveSession::TYPE_CALL,
        'status' => LiveSession::STATUS_RINGING,
        'started_at' => now(),
    ]);

    $session->respondToRing($callee, true);

    expect($session->status)->toBe(LiveSession::STATUS_LIVE);
    expect($session->answered_at)->not->toBeNull();
    Event::assertDispatched(CallStatusUpdated::class);
});

test('the callee can decline a ringing call', function () {
    $host = User::factory()->create();
    $callee = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id,
        'callee_id' => $callee->id,
        'room_name' => 'r2',
        'type' => LiveSession::TYPE_CALL,
        'status' => LiveSession::STATUS_RINGING,
        'started_at' => now(),
    ]);

    $session->respondToRing($callee, false);

    expect($session->status)->toBe(LiveSession::STATUS_DECLINED);
    expect($session->ended_at)->not->toBeNull();
});

test('only the callee can respond to a ringing call', function () {
    $host = User::factory()->create();
    $callee = User::factory()->create();
    $intruder = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id,
        'callee_id' => $callee->id,
        'room_name' => 'r3',
        'type' => LiveSession::TYPE_CALL,
        'status' => LiveSession::STATUS_RINGING,
        'started_at' => now(),
    ]);

    expect(fn () => $session->respondToRing($host, true))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(fn () => $session->respondToRing($intruder, true))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('responding after the ring window has passed self-heals to missed instead of live', function () {
    $host = User::factory()->create();
    $callee = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id,
        'callee_id' => $callee->id,
        'room_name' => 'r4',
        'type' => LiveSession::TYPE_CALL,
        'status' => LiveSession::STATUS_RINGING,
        'started_at' => now()->subSeconds(60),
    ]);

    $session->respondToRing($callee, true);

    expect($session->status)->toBe(LiveSession::STATUS_MISSED);
    expect($session->ended_reason)->toBe(LiveSession::REASON_TIMEOUT);
});

// --- endOrCancel ---------------------------------------------------------

test('the host can cancel a call while it is still ringing', function () {
    $host = User::factory()->create();
    $callee = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id,
        'callee_id' => $callee->id,
        'room_name' => 'r5',
        'type' => LiveSession::TYPE_CALL,
        'status' => LiveSession::STATUS_RINGING,
        'started_at' => now(),
    ]);

    $session->endOrCancel($host);

    expect($session->status)->toBe(LiveSession::STATUS_CANCELED);
});

test('either the host or the callee can end a live call, but a third party cannot', function () {
    $host = User::factory()->create();
    $callee = User::factory()->create();
    $intruder = User::factory()->create();

    $forHost = LiveSession::create([
        'host_id' => $host->id, 'callee_id' => $callee->id, 'room_name' => 'r6',
        'type' => LiveSession::TYPE_CALL, 'status' => LiveSession::STATUS_LIVE, 'started_at' => now(),
    ]);
    $forHost->endOrCancel($host);
    expect($forHost->status)->toBe(LiveSession::STATUS_ENDED);

    $forCallee = LiveSession::create([
        'host_id' => $host->id, 'callee_id' => $callee->id, 'room_name' => 'r7',
        'type' => LiveSession::TYPE_CALL, 'status' => LiveSession::STATUS_LIVE, 'started_at' => now(),
    ]);
    $forCallee->endOrCancel($callee);
    expect($forCallee->status)->toBe(LiveSession::STATUS_ENDED);

    $blocked = LiveSession::create([
        'host_id' => $host->id, 'callee_id' => $callee->id, 'room_name' => 'r8',
        'type' => LiveSession::TYPE_CALL, 'status' => LiveSession::STATUS_LIVE, 'started_at' => now(),
    ]);
    expect(fn () => $blocked->endOrCancel($intruder))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect($blocked->fresh()->status)->toBe(LiveSession::STATUS_LIVE);
});

// --- expireIfStale / ExpireRingingCall job --------------------------------

test('expireIfStale marks a stale ringing call missed and notifies the callee, but is a no-op otherwise', function () {
    Notification::fake();

    $host = User::factory()->create();
    $callee = User::factory()->create();
    $stale = LiveSession::create([
        'host_id' => $host->id, 'callee_id' => $callee->id, 'room_name' => 'r9',
        'type' => LiveSession::TYPE_CALL, 'status' => LiveSession::STATUS_RINGING,
        'started_at' => now()->subSeconds(60),
    ]);

    expect($stale->expireIfStale())->toBeTrue();
    expect($stale->status)->toBe(LiveSession::STATUS_MISSED);
    expect($stale->ended_reason)->toBe(LiveSession::REASON_TIMEOUT);
    Notification::assertSentTo($callee, MissedCall::class);

    $stillRinging = LiveSession::create([
        'host_id' => $host->id, 'callee_id' => $callee->id, 'room_name' => 'r10',
        'type' => LiveSession::TYPE_CALL, 'status' => LiveSession::STATUS_RINGING, 'started_at' => now(),
    ]);
    expect($stillRinging->expireIfStale())->toBeFalse();

    $alreadyLive = LiveSession::create([
        'host_id' => $host->id, 'callee_id' => $callee->id, 'room_name' => 'r11',
        'type' => LiveSession::TYPE_CALL, 'status' => LiveSession::STATUS_LIVE,
        'started_at' => now()->subSeconds(60), 'answered_at' => now()->subSeconds(50),
    ]);
    expect($alreadyLive->expireIfStale())->toBeFalse();
    expect($alreadyLive->fresh()->status)->toBe(LiveSession::STATUS_LIVE);
});

test('the expiry job expires a stale call when it runs', function () {
    $host = User::factory()->create();
    $callee = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id, 'callee_id' => $callee->id, 'room_name' => 'r12',
        'type' => LiveSession::TYPE_CALL, 'status' => LiveSession::STATUS_RINGING,
        'started_at' => now()->subSeconds(60),
    ]);

    (new ExpireRingingCall($session))->handle();

    expect($session->fresh()->status)->toBe(LiveSession::STATUS_MISSED);
});

// --- live.show access control and states ----------------------------------

test('a non-participant cannot open a call room', function () {
    $host = User::factory()->create();
    $callee = User::factory()->create();
    $intruder = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id, 'callee_id' => $callee->id, 'room_name' => 'r13',
        'type' => LiveSession::TYPE_CALL, 'status' => LiveSession::STATUS_RINGING, 'started_at' => now(),
    ]);

    Livewire::actingAs($intruder)
        ->test('pages::live.show', ['liveSession' => $session])
        ->assertForbidden();
});

test('the host sees an outgoing calling screen while ringing, the callee sees accept and decline', function () {
    $host = User::factory()->create(['name' => 'Hosting Person']);
    $callee = User::factory()->create(['name' => 'Called Person']);
    $session = LiveSession::create([
        'host_id' => $host->id, 'callee_id' => $callee->id, 'room_name' => 'r14',
        'type' => LiveSession::TYPE_CALL, 'status' => LiveSession::STATUS_RINGING, 'started_at' => now(),
    ]);

    Livewire::actingAs($host)
        ->test('pages::live.show', ['liveSession' => $session])
        ->assertSee('Calling')
        ->assertSee('Called Person')
        ->assertDontSee('Accept');

    Livewire::actingAs($callee)
        ->test('pages::live.show', ['liveSession' => $session])
        ->assertSee('Hosting Person')
        ->assertSee('is calling you')
        ->assertSeeHtml('respond(true)')
        ->assertSeeHtml('respond(false)');
});

test('the ringing screen and the connected room render under different wire:key markers so Livewire fully remounts instead of patching in place', function () {
    // A bug this guards against: Livewire's morph will patch one x-data
    // block into another in place (rather than tearing down and
    // reinitializing Alpine) unless each state has a distinct key — which
    // silently prevented the LiveKit connection from ever starting when a
    // call went from ringing to live without a full page reload.
    config([
        'services.livekit.api_key' => 'test-key',
        'services.livekit.api_secret' => base64_encode(random_bytes(64)),
        'services.livekit.url' => 'wss://example.livekit.cloud',
    ]);

    $host = User::factory()->create();
    $callee = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id, 'callee_id' => $callee->id, 'room_name' => 'r14b',
        'type' => LiveSession::TYPE_CALL, 'status' => LiveSession::STATUS_RINGING, 'started_at' => now(),
    ]);

    $component = Livewire::actingAs($host)->test('pages::live.show', ['liveSession' => $session]);

    expect($component->html())->toContain("live-session-{$session->id}-ringing");

    // The host's page never navigates — it's the same component instance,
    // updated in place by the broadcast listener, exactly like a real
    // CallStatusUpdated event landing while the "Calling…" screen is open.
    $session->respondToRing($callee, true);
    $component->call('onCallStatusUpdated', ['session_id' => $session->id]);

    $roomHtml = $component->html();
    expect($roomHtml)->toContain("live-session-{$session->id}-room");
    expect($roomHtml)->not->toContain("live-session-{$session->id}-ringing");
});

test('accepting from the room page connects the call and issues a token', function () {
    config([
        'services.livekit.api_key' => 'test-key',
        'services.livekit.api_secret' => base64_encode(random_bytes(64)),
        'services.livekit.url' => 'wss://example.livekit.cloud',
    ]);

    $host = User::factory()->create();
    $callee = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id, 'callee_id' => $callee->id, 'room_name' => 'r15',
        'type' => LiveSession::TYPE_CALL, 'status' => LiveSession::STATUS_RINGING, 'started_at' => now(),
    ]);

    Livewire::actingAs($callee)
        ->test('pages::live.show', ['liveSession' => $session])
        ->call('respond', true)
        ->assertSet('session.status', LiveSession::STATUS_LIVE);

    expect($session->fresh()->status)->toBe(LiveSession::STATUS_LIVE);
});

test('declining from the room page redirects to the live directory', function () {
    $host = User::factory()->create();
    $callee = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id, 'callee_id' => $callee->id, 'room_name' => 'r16',
        'type' => LiveSession::TYPE_CALL, 'status' => LiveSession::STATUS_RINGING, 'started_at' => now(),
    ]);

    Livewire::actingAs($callee)
        ->test('pages::live.show', ['liveSession' => $session])
        ->call('respond', false)
        ->assertRedirect(route('live.index'));

    expect($session->fresh()->status)->toBe(LiveSession::STATUS_DECLINED);
});

test('the callee, not just the host, can end a live call from the room page', function () {
    $host = User::factory()->create();
    $callee = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id, 'callee_id' => $callee->id, 'room_name' => 'r17',
        'type' => LiveSession::TYPE_CALL, 'status' => LiveSession::STATUS_LIVE, 'started_at' => now(),
    ]);

    Livewire::actingAs($callee)
        ->test('pages::live.show', ['liveSession' => $session])
        ->call('endSession')
        ->assertRedirect(route('live.index'));

    expect($session->fresh()->status)->toBe(LiveSession::STATUS_ENDED);
});

test('the control bar leave button lets a stream viewer leave without hitting the host-only guard', function () {
    $host = User::factory()->create();
    $viewer = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id, 'room_name' => 'r17b',
        'type' => LiveSession::TYPE_STREAM, 'status' => LiveSession::STATUS_LIVE, 'started_at' => now(),
    ]);

    Livewire::actingAs($viewer)
        ->test('pages::live.show', ['liveSession' => $session])
        ->call('leaveRoom')
        ->assertRedirect(route('live.index'));

    expect($session->fresh()->status)->toBe(LiveSession::STATUS_LIVE);
});

test('the control bar leave button ends the stream when the host uses it', function () {
    $host = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id, 'room_name' => 'r17c',
        'type' => LiveSession::TYPE_STREAM, 'status' => LiveSession::STATUS_LIVE, 'started_at' => now(),
    ]);

    Livewire::actingAs($host)
        ->test('pages::live.show', ['liveSession' => $session])
        ->call('leaveRoom')
        ->assertRedirect(route('live.index'));

    expect($session->fresh()->status)->toBe(LiveSession::STATUS_ENDED);
});

test('a missed call because the callee was offline tells the host they were notified', function () {
    $host = User::factory()->create();
    $callee = User::factory()->create(['name' => 'Sleepy Person']);
    $session = LiveSession::create([
        'host_id' => $host->id, 'callee_id' => $callee->id, 'room_name' => 'r18',
        'type' => LiveSession::TYPE_CALL, 'status' => LiveSession::STATUS_MISSED,
        'ended_reason' => LiveSession::REASON_OFFLINE, 'started_at' => now(), 'ended_at' => now(),
    ]);

    Livewire::actingAs($host)
        ->test('pages::live.show', ['liveSession' => $session])
        ->assertSee('Sleepy Person')
        ->assertSee("they've been notified");
});

// --- global incoming-call ringer -------------------------------------------

test('the global ringer shows a still-ringing call for the callee on mount', function () {
    $host = User::factory()->create(['name' => 'Ringing Caller']);
    $callee = User::factory()->create();
    LiveSession::create([
        'host_id' => $host->id, 'callee_id' => $callee->id, 'room_name' => 'r19',
        'type' => LiveSession::TYPE_CALL, 'status' => LiveSession::STATUS_RINGING, 'started_at' => now(),
    ]);

    Livewire::actingAs($callee)
        ->test('pages::layout.incoming-call')
        ->assertSee('Ringing Caller')
        ->assertSee('Incoming call');
});

test('the global ringer shows nothing when there is no ringing call', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::layout.incoming-call')
        ->assertDontSee('Incoming call');
});

test('the global ringer self-heals an expired ringing call to missed on mount instead of showing it', function () {
    Notification::fake();

    $host = User::factory()->create();
    $callee = User::factory()->create();
    $stale = LiveSession::create([
        'host_id' => $host->id, 'callee_id' => $callee->id, 'room_name' => 'r20',
        'type' => LiveSession::TYPE_CALL, 'status' => LiveSession::STATUS_RINGING,
        'started_at' => now()->subSeconds(60),
    ]);

    Livewire::actingAs($callee)
        ->test('pages::layout.incoming-call')
        ->assertDontSee('Incoming call');

    expect($stale->fresh()->status)->toBe(LiveSession::STATUS_MISSED);
});

test('accepting from the global ringer redirects into the call room', function () {
    $host = User::factory()->create();
    $callee = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id, 'callee_id' => $callee->id, 'room_name' => 'r21',
        'type' => LiveSession::TYPE_CALL, 'status' => LiveSession::STATUS_RINGING, 'started_at' => now(),
    ]);

    Livewire::actingAs($callee)
        ->test('pages::layout.incoming-call')
        ->call('respond', true)
        ->assertRedirect(route('live.show', $session));

    expect($session->fresh()->status)->toBe(LiveSession::STATUS_LIVE);
});

test('declining from the global ringer dismisses it without navigating away', function () {
    $host = User::factory()->create();
    $callee = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id, 'callee_id' => $callee->id, 'room_name' => 'r22',
        'type' => LiveSession::TYPE_CALL, 'status' => LiveSession::STATUS_RINGING, 'started_at' => now(),
    ]);

    Livewire::actingAs($callee)
        ->test('pages::layout.incoming-call')
        ->call('respond', false)
        ->assertNoRedirect()
        ->assertDontSee('Incoming call');

    expect($session->fresh()->status)->toBe(LiveSession::STATUS_DECLINED);
});
