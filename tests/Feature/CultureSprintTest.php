<?php

use App\Events\CallStatusUpdated;
use App\Models\BridgeScoreEvent;
use App\Models\CultureSprintPool;
use App\Models\Heritage;
use App\Models\Interest;
use App\Models\Language;
use App\Models\LiveSession;
use App\Models\User;
use App\Notifications\MissedCall;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

// --- CultureSprintPool::findWaitingPartnerFor -------------------------------

test('finds the longest-waiting other online user on the same topic', function () {
    $me = User::factory()->create();
    $older = User::factory()->create(['last_seen_at' => now()]);
    $newer = User::factory()->create(['last_seen_at' => now()]);

    CultureSprintPool::create(['user_id' => $older->id, 'topic' => 'Food', 'created_at' => now()->subMinutes(2)]);
    CultureSprintPool::create(['user_id' => $newer->id, 'topic' => 'Food', 'created_at' => now()->subSeconds(10)]);

    $partner = CultureSprintPool::findWaitingPartnerFor($me, 'Food', null);

    expect($partner->id)->toBe($older->id);
});

test('never matches a user with their own pool entry', function () {
    $me = User::factory()->create(['last_seen_at' => now()]);
    CultureSprintPool::create(['user_id' => $me->id, 'topic' => 'Food']);

    expect(CultureSprintPool::findWaitingPartnerFor($me, 'Food', null))->toBeNull();
});

test('does not match against a waiting user who has gone offline', function () {
    $me = User::factory()->create();
    $offline = User::factory()->create(['last_seen_at' => now()->subMinutes(10)]);
    CultureSprintPool::create(['user_id' => $offline->id, 'topic' => 'Food']);

    expect(CultureSprintPool::findWaitingPartnerFor($me, 'Food', null))->toBeNull();
});

test('does not match across different topics', function () {
    $me = User::factory()->create();
    $waitingOnAnotherTopic = User::factory()->create(['last_seen_at' => now()]);
    CultureSprintPool::create(['user_id' => $waitingOnAnotherTopic->id, 'topic' => 'Music']);

    expect(CultureSprintPool::findWaitingPartnerFor($me, 'Food', null))->toBeNull();
});

test('a region filter only matches a candidate whose own form entry is that region or blank', function () {
    $me = User::factory()->create();

    $askedForEurope = User::factory()->create(['last_seen_at' => now()]);
    CultureSprintPool::create(['user_id' => $askedForEurope->id, 'topic' => 'Food', 'region' => 'Europe']);

    $askedForAsia = User::factory()->create(['last_seen_at' => now()]);
    CultureSprintPool::create(['user_id' => $askedForAsia->id, 'topic' => 'Food', 'region' => 'Asia']);

    $partner = CultureSprintPool::findWaitingPartnerFor($me, 'Food', 'Asia');

    expect($partner->id)->toBe($askedForAsia->id);
});

test('a specific region search still matches a candidate who left their region blank', function () {
    $me = User::factory()->create();

    $anywhere = User::factory()->create(['last_seen_at' => now()]);
    CultureSprintPool::create(['user_id' => $anywhere->id, 'topic' => 'Food', 'region' => null]);

    expect(CultureSprintPool::findWaitingPartnerFor($me, 'Food', 'Asia')->id)->toBe($anywhere->id);
});

test('a blank region search matches a candidate regardless of what region they asked for', function () {
    $me = User::factory()->create();

    $askedForAsia = User::factory()->create(['last_seen_at' => now()]);
    CultureSprintPool::create(['user_id' => $askedForAsia->id, 'topic' => 'Food', 'region' => 'Asia']);

    expect(CultureSprintPool::findWaitingPartnerFor($me, 'Food', null)->id)->toBe($askedForAsia->id);
});

test('matching ignores heritage, language, and interests entirely', function () {
    $mismatchedHeritage = Heritage::create(['name' => 'Irish', 'slug' => 'irish-independent', 'region' => 'Europe']);
    $spanish = Language::create(['name' => 'Spanish', 'slug' => 'spanish-independent']);
    $cooking = Interest::create(['name' => 'Cooking', 'slug' => 'cooking-independent']);

    // The seeker shares nothing with the waiting candidate on heritage,
    // language, or interest — none of that should matter to the match.
    $me = User::factory()->create();
    $me->languages()->attach($spanish->id);
    $me->interests()->attach($cooking->id);

    $candidate = User::factory()->create(['last_seen_at' => now()]);
    $candidate->heritages()->attach($mismatchedHeritage->id);
    CultureSprintPool::create(['user_id' => $candidate->id, 'topic' => 'Food', 'region' => 'Asia']);

    expect(CultureSprintPool::findWaitingPartnerFor($me, 'Food', 'Asia')?->id)->toBe($candidate->id);
});

// --- CultureSprintPool::claimWaitingPartnerFor (atomic) ---------------------

test('claiming a waiting partner returns them and removes both pool rows', function () {
    $waiting = User::factory()->create(['last_seen_at' => now()]);
    CultureSprintPool::create(['user_id' => $waiting->id, 'topic' => 'Food']);

    $me = User::factory()->create();
    CultureSprintPool::create(['user_id' => $me->id, 'topic' => 'Food']);

    $partner = CultureSprintPool::claimWaitingPartnerFor($me, 'Food', null);

    expect($partner->id)->toBe($waiting->id);
    expect(CultureSprintPool::where('user_id', $waiting->id)->exists())->toBeFalse();
    expect(CultureSprintPool::where('user_id', $me->id)->exists())->toBeFalse();
});

test('claiming with nobody waiting returns null and leaves rows untouched', function () {
    $me = User::factory()->create();
    CultureSprintPool::create(['user_id' => $me->id, 'topic' => 'Food']);

    expect(CultureSprintPool::claimWaitingPartnerFor($me, 'Food', null))->toBeNull();
    expect(CultureSprintPool::where('user_id', $me->id)->exists())->toBeTrue();
});

test('pruneStale removes only pool rows past the staleness window', function () {
    config(['culture_sprints.pool_stale_minutes' => 5]);

    $stale = CultureSprintPool::create(['user_id' => User::factory()->create()->id, 'topic' => 'Food']);
    $stale->forceFill(['created_at' => now()->subMinutes(10)])->save();

    $fresh = CultureSprintPool::create(['user_id' => User::factory()->create()->id, 'topic' => 'Food']);

    CultureSprintPool::pruneStale();

    expect(CultureSprintPool::find($stale->id))->toBeNull();
    expect(CultureSprintPool::find($fresh->id))->not->toBeNull();
});

// --- LiveSession::startSprintMatch ------------------------------------------

test('starting a sprint match creates a ringing session with a culture word and broadcasts it', function () {
    Event::fake([CallStatusUpdated::class]);

    $a = User::factory()->create();
    $b = User::factory()->create();

    $session = LiveSession::startSprintMatch($a, $b, 'Food');

    expect($session->type)->toBe(LiveSession::TYPE_SPRINT);
    expect($session->status)->toBe(LiveSession::STATUS_RINGING);
    expect($session->culture_word)->toBe('Food');
    expect($session->host_id)->toBe($a->id);
    expect($session->callee_id)->toBe($b->id);

    Event::assertDispatched(CallStatusUpdated::class, fn ($e) => $e->session->is($session));
});

// --- respondToSprint ---------------------------------------------------------

test('a sprint only goes live once both sides have accepted', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $session = LiveSession::startSprintMatch($a, $b, 'Food');

    $session->respondToSprint($a, true);

    expect($session->status)->toBe(LiveSession::STATUS_RINGING);
    expect($session->host_accepted_at)->not->toBeNull();
    expect($session->callee_accepted_at)->toBeNull();

    $session->respondToSprint($b, true);

    expect($session->status)->toBe(LiveSession::STATUS_LIVE);
    expect($session->answered_at)->not->toBeNull();
});

test('either side declining a sprint match ends it for both', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $session = LiveSession::startSprintMatch($a, $b, 'Food');

    $session->respondToSprint($a, true);
    $session->respondToSprint($b, false);

    expect($session->status)->toBe(LiveSession::STATUS_DECLINED);
});

test('a user who already accepted can still back out while waiting on their partner', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $session = LiveSession::startSprintMatch($a, $b, 'Food');

    $session->respondToSprint($a, true);
    expect($session->host_accepted_at)->not->toBeNull();

    // Changed their mind before the other side ever responded.
    $session->respondToSprint($a, false);

    expect($session->status)->toBe(LiveSession::STATUS_DECLINED);
});

test('only a participant can respond to a sprint match', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $intruder = User::factory()->create();
    $session = LiveSession::startSprintMatch($a, $b, 'Food');

    expect(fn () => $session->respondToSprint($intruder, true))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('responding after the accept window closes self-heals to missed instead of live', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $session = LiveSession::startSprintMatch($a, $b, 'Food');
    $session->forceFill(['started_at' => now()->subSeconds(60)])->save();

    $session->respondToSprint($a, true);

    expect($session->status)->toBe(LiveSession::STATUS_MISSED);
    expect($session->ended_reason)->toBe(LiveSession::REASON_TIMEOUT);
});

test('a timed-out sprint does not send the call-style missed notification', function () {
    Notification::fake();

    $a = User::factory()->create();
    $b = User::factory()->create();
    $session = LiveSession::startSprintMatch($a, $b, 'Food');
    $session->forceFill(['started_at' => now()->subSeconds(60)])->save();

    $session->expireIfStale();

    Notification::assertNothingSentTo($b);
    Notification::assertNothingSentTo($a);
});

// --- completeSprint -----------------------------------------------------

test('completing a sprint ends it and awards Bridge Score to both sides', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $session = LiveSession::startSprintMatch($a, $b, 'Food');
    $session->respondToSprint($a, true);
    $session->respondToSprint($b, true);

    $session->completeSprint($a);

    expect($session->status)->toBe(LiveSession::STATUS_ENDED);
    expect(BridgeScoreEvent::where('user_id', $a->id)->where('reason', 'culture_sprint_completed')->exists())->toBeTrue();
    expect(BridgeScoreEvent::where('user_id', $b->id)->where('reason', 'culture_sprint_completed')->exists())->toBeTrue();
});

test('completing a sprint between people of different heritages adds the cross-heritage bonus', function () {
    $nigerian = Heritage::create(['name' => 'Nigerian', 'slug' => 'nigerian']);
    $japanese = Heritage::create(['name' => 'Japanese', 'slug' => 'japanese']);

    $a = User::factory()->create();
    $a->heritages()->attach($nigerian->id);
    $b = User::factory()->create();
    $b->heritages()->attach($japanese->id);

    $session = LiveSession::startSprintMatch($a, $b, 'Food');
    $session->respondToSprint($a, true);
    $session->respondToSprint($b, true);

    $session->completeSprint($a);

    expect(BridgeScoreEvent::where('user_id', $a->id)->where('reason', 'culture_sprint_cross_heritage_bonus')->exists())->toBeTrue();
});

test('completeSprint is a no-op on a session that is not live', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $session = LiveSession::startSprintMatch($a, $b, 'Food');

    $session->completeSprint($a);

    expect($session->fresh()->status)->toBe(LiveSession::STATUS_RINGING);
    expect(BridgeScoreEvent::where('user_id', $a->id)->exists())->toBeFalse();
});

test('only a participant can complete a sprint', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $intruder = User::factory()->create();
    $session = LiveSession::startSprintMatch($a, $b, 'Food');
    $session->respondToSprint($a, true);
    $session->respondToSprint($b, true);

    expect(fn () => $session->completeSprint($intruder))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect($session->fresh()->status)->toBe(LiveSession::STATUS_LIVE);
});

// --- requestRematch ------------------------------------------------------

test('requesting a rematch creates a new ringing session with the requester pre-accepted', function () {
    Event::fake([CallStatusUpdated::class]);

    $a = User::factory()->create();
    $b = User::factory()->create();
    $session = LiveSession::startSprintMatch($a, $b, 'Food');
    $session->respondToSprint($a, true);
    $session->respondToSprint($b, true);
    $session->completeSprint($a);

    $rematch = LiveSession::requestRematch($session, $a);

    expect($rematch->id)->not->toBe($session->id);
    expect($rematch->type)->toBe(LiveSession::TYPE_SPRINT);
    expect($rematch->status)->toBe(LiveSession::STATUS_RINGING);
    expect($rematch->culture_word)->toBe('Food');
    expect($rematch->host_id)->toBe($a->id);
    expect($rematch->callee_id)->toBe($b->id);
    expect($rematch->host_accepted_at)->not->toBeNull();
    expect($rematch->callee_accepted_at)->toBeNull();

    Event::assertDispatched(CallStatusUpdated::class, fn ($e) => $e->session->is($rematch));
});

test('requesting a rematch as the original callee pre-accepts their own side', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $session = LiveSession::startSprintMatch($a, $b, 'Food');
    $session->respondToSprint($a, true);
    $session->respondToSprint($b, true);
    $session->completeSprint($a);

    $rematch = LiveSession::requestRematch($session, $b);

    // Roles carry over from the original match — the requester keeps
    // whichever side they were on, and that's the side marked accepted.
    expect($rematch->host_id)->toBe($a->id);
    expect($rematch->callee_id)->toBe($b->id);
    expect($rematch->host_accepted_at)->toBeNull();
    expect($rematch->callee_accepted_at)->not->toBeNull();
});

test('a single accept from the partner is enough to go live on a rematch', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $session = LiveSession::startSprintMatch($a, $b, 'Food');
    $session->respondToSprint($a, true);
    $session->respondToSprint($b, true);
    $session->completeSprint($a);

    $rematch = LiveSession::requestRematch($session, $a);
    $rematch->respondToSprint($b, true);

    expect($rematch->status)->toBe(LiveSession::STATUS_LIVE);
});

test('a rematch can only be requested for a completed sprint', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $session = LiveSession::startSprintMatch($a, $b, 'Food');

    expect(fn () => LiveSession::requestRematch($session, $a))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('only a participant of the original sprint can request a rematch', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $intruder = User::factory()->create();
    $session = LiveSession::startSprintMatch($a, $b, 'Food');
    $session->respondToSprint($a, true);
    $session->respondToSprint($b, true);
    $session->completeSprint($a);

    expect(fn () => LiveSession::requestRematch($session, $intruder))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

// --- canPublish ---------------------------------------------------------

test('both sides of a sprint can publish audio/video', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $session = LiveSession::startSprintMatch($a, $b, 'Food');

    expect($session->canPublish($a))->toBeTrue();
    expect($session->canPublish($b))->toBeTrue();
});
