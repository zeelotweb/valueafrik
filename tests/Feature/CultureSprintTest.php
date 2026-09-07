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

test('a region filter only matches a candidate whose own heritage is actually from that region', function () {
    $asia = Heritage::create(['name' => 'Japanese', 'slug' => 'japanese-r', 'region' => 'Asia']);
    $europe = Heritage::create(['name' => 'Irish', 'slug' => 'irish-r', 'region' => 'Europe']);

    $me = User::factory()->create();

    $fromEurope = User::factory()->create(['last_seen_at' => now()]);
    $fromEurope->heritages()->attach($europe->id);
    CultureSprintPool::create(['user_id' => $fromEurope->id, 'topic' => 'Food']);

    $fromAsia = User::factory()->create(['last_seen_at' => now()]);
    $fromAsia->heritages()->attach($asia->id);
    CultureSprintPool::create(['user_id' => $fromAsia->id, 'topic' => 'Food']);

    $partner = CultureSprintPool::findWaitingPartnerFor($me, 'Food', 'Asia');

    expect($partner->id)->toBe($fromAsia->id);
});

test('a candidate who asked for a region themselves is skipped unless the seeker actually satisfies it', function () {
    $asia = Heritage::create(['name' => 'Japanese', 'slug' => 'japanese-r2', 'region' => 'Asia']);
    $europe = Heritage::create(['name' => 'Irish', 'slug' => 'irish-r2', 'region' => 'Europe']);

    $seekerFromEurope = User::factory()->create();
    $seekerFromEurope->heritages()->attach($europe->id);

    $picky = User::factory()->create(['last_seen_at' => now()]);
    CultureSprintPool::create(['user_id' => $picky->id, 'topic' => 'Food', 'region' => 'Asia']);

    // The seeker isn't from Asia, so they can't satisfy what the waiting
    // person asked for — no match, even with no region filter of their own.
    expect(CultureSprintPool::findWaitingPartnerFor($seekerFromEurope, 'Food', null))->toBeNull();

    $seekerFromAsia = User::factory()->create();
    $seekerFromAsia->heritages()->attach($asia->id);

    expect(CultureSprintPool::findWaitingPartnerFor($seekerFromAsia, 'Food', null)->id)->toBe($picky->id);
});

test('prefers a waiting partner who shares a language over one who does not', function () {
    $spanish = Language::create(['name' => 'Spanish', 'slug' => 'spanish']);

    $me = User::factory()->create();
    $me->languages()->attach($spanish->id);

    $noSharedLanguage = User::factory()->create(['last_seen_at' => now()]);
    CultureSprintPool::create(['user_id' => $noSharedLanguage->id, 'topic' => 'Food', 'created_at' => now()->subMinutes(1)]);

    $sharedLanguage = User::factory()->create(['last_seen_at' => now()]);
    $sharedLanguage->languages()->attach($spanish->id);
    CultureSprintPool::create(['user_id' => $sharedLanguage->id, 'topic' => 'Food', 'created_at' => now()->subSeconds(5)]);

    $partner = CultureSprintPool::findWaitingPartnerFor($me, 'Food', null);

    expect($partner->id)->toBe($sharedLanguage->id);
});

test('prefers a waiting partner who shares an interest over one who does not', function () {
    $cooking = Interest::create(['name' => 'Cooking', 'slug' => 'cooking']);

    $me = User::factory()->create();
    $me->interests()->attach($cooking->id);

    $noSharedInterest = User::factory()->create(['last_seen_at' => now()]);
    CultureSprintPool::create(['user_id' => $noSharedInterest->id, 'topic' => 'Food', 'created_at' => now()->subMinutes(1)]);

    $sharedInterest = User::factory()->create(['last_seen_at' => now()]);
    $sharedInterest->interests()->attach($cooking->id);
    CultureSprintPool::create(['user_id' => $sharedInterest->id, 'topic' => 'Food', 'created_at' => now()->subSeconds(5)]);

    expect(CultureSprintPool::findWaitingPartnerFor($me, 'Food', null)->id)->toBe($sharedInterest->id);
});

test('falls back to any online waiting user when no shared language or interest exists', function () {
    $spanish = Language::create(['name' => 'Spanish', 'slug' => 'spanish']);
    $me = User::factory()->create();
    $me->languages()->attach($spanish->id);

    $noSharedLanguage = User::factory()->create(['last_seen_at' => now()]);
    CultureSprintPool::create(['user_id' => $noSharedLanguage->id, 'topic' => 'Food']);

    expect(CultureSprintPool::findWaitingPartnerFor($me, 'Food', null)->id)->toBe($noSharedLanguage->id);
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

    $session->completeSprint();

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

    $session->completeSprint();

    expect(BridgeScoreEvent::where('user_id', $a->id)->where('reason', 'culture_sprint_cross_heritage_bonus')->exists())->toBeTrue();
});

test('completeSprint is a no-op on a session that is not live', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $session = LiveSession::startSprintMatch($a, $b, 'Food');

    $session->completeSprint();

    expect($session->fresh()->status)->toBe(LiveSession::STATUS_RINGING);
    expect(BridgeScoreEvent::where('user_id', $a->id)->exists())->toBeFalse();
});

// --- canPublish ---------------------------------------------------------

test('both sides of a sprint can publish audio/video', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $session = LiveSession::startSprintMatch($a, $b, 'Food');

    expect($session->canPublish($a))->toBeTrue();
    expect($session->canPublish($b))->toBeTrue();
});
