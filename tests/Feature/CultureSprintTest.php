<?php

use App\Events\CallStatusUpdated;
use App\Models\BridgeScoreEvent;
use App\Models\CultureSprintPool;
use App\Models\Heritage;
use App\Models\Language;
use App\Models\LiveSession;
use App\Models\User;
use App\Notifications\MissedCall;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

// --- CultureSprintPool::findWaitingPartnerFor -------------------------------

test('finds the longest-waiting other online user', function () {
    $me = User::factory()->create();
    $older = User::factory()->create(['last_seen_at' => now()]);
    $newer = User::factory()->create(['last_seen_at' => now()]);

    CultureSprintPool::create(['user_id' => $older->id, 'created_at' => now()->subMinutes(2)]);
    CultureSprintPool::create(['user_id' => $newer->id, 'created_at' => now()->subSeconds(10)]);

    $partner = CultureSprintPool::findWaitingPartnerFor($me);

    expect($partner->id)->toBe($older->id);
});

test('never matches a user with their own pool entry', function () {
    $me = User::factory()->create(['last_seen_at' => now()]);
    CultureSprintPool::create(['user_id' => $me->id]);

    expect(CultureSprintPool::findWaitingPartnerFor($me))->toBeNull();
});

test('does not match against a waiting user who has gone offline', function () {
    $me = User::factory()->create();
    $offline = User::factory()->create(['last_seen_at' => now()->subMinutes(10)]);
    CultureSprintPool::create(['user_id' => $offline->id]);

    expect(CultureSprintPool::findWaitingPartnerFor($me))->toBeNull();
});

test('prefers a waiting partner who shares a language over one who does not', function () {
    $spanish = Language::create(['name' => 'Spanish', 'slug' => 'spanish']);

    $me = User::factory()->create();
    $me->languages()->attach($spanish->id);

    $noSharedLanguage = User::factory()->create(['last_seen_at' => now()]);
    CultureSprintPool::create(['user_id' => $noSharedLanguage->id, 'created_at' => now()->subMinutes(1)]);

    $sharedLanguage = User::factory()->create(['last_seen_at' => now()]);
    $sharedLanguage->languages()->attach($spanish->id);
    CultureSprintPool::create(['user_id' => $sharedLanguage->id, 'created_at' => now()->subSeconds(5)]);

    $partner = CultureSprintPool::findWaitingPartnerFor($me);

    expect($partner->id)->toBe($sharedLanguage->id);
});

test('falls back to any online waiting user when no shared language exists', function () {
    $spanish = Language::create(['name' => 'Spanish', 'slug' => 'spanish']);
    $me = User::factory()->create();
    $me->languages()->attach($spanish->id);

    $noSharedLanguage = User::factory()->create(['last_seen_at' => now()]);
    CultureSprintPool::create(['user_id' => $noSharedLanguage->id]);

    expect(CultureSprintPool::findWaitingPartnerFor($me)->id)->toBe($noSharedLanguage->id);
});

test('pruneStale removes only pool rows past the staleness window', function () {
    config(['culture_sprints.pool_stale_minutes' => 5]);

    $stale = CultureSprintPool::create(['user_id' => User::factory()->create()->id]);
    $stale->forceFill(['created_at' => now()->subMinutes(10)])->save();

    $fresh = CultureSprintPool::create(['user_id' => User::factory()->create()->id]);

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
