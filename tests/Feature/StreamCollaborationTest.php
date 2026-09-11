<?php

use App\Events\StreamCollaborationUpdated;
use App\Models\BridgeScoreEvent;
use App\Models\LiveSession;
use App\Models\User;
use App\Services\LiveKitToken;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

function eligibleCollaborator(): User
{
    $user = User::factory()->create();
    BridgeScoreEvent::create([
        'user_id' => $user->id,
        'points' => config('streams.collaboration_bridge_score_threshold'),
        'reason' => 'test',
    ]);

    return $user;
}

test('a viewer below the bridge score threshold and without a subscription cannot request to collaborate', function () {
    $host = User::factory()->create();
    $viewer = User::factory()->create();
    $session = LiveSession::startStream($host);

    expect($viewer->canCollaborateOnStreams())->toBeFalse();
    expect(fn () => $session->requestCollaboration($viewer))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('a viewer who has crossed the bridge score threshold can request to collaborate', function () {
    Event::fake([StreamCollaborationUpdated::class]);

    $host = User::factory()->create();
    $viewer = eligibleCollaborator();
    $session = LiveSession::startStream($host);

    $session->requestCollaboration($viewer);

    expect($session->hasRequestedCollaboration($viewer))->toBeTrue();
    expect($session->isApprovedCollaborator($viewer))->toBeFalse();

    Event::assertDispatched(StreamCollaborationUpdated::class, fn ($e) => $e->collaborator->is($viewer) && $e->status === 'requested');
});

test('requesting twice does not create a duplicate row', function () {
    $host = User::factory()->create();
    $viewer = eligibleCollaborator();
    $session = LiveSession::startStream($host);

    $session->requestCollaboration($viewer);
    $session->requestCollaboration($viewer);

    expect($session->collaborators()->where('user_id', $viewer->id)->count())->toBe(1);
});

test('the host cannot request to collaborate on their own stream', function () {
    $host = eligibleCollaborator();
    $session = LiveSession::startStream($host);

    expect(fn () => $session->requestCollaboration($host))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('a viewer cannot request to collaborate on a followers-only stream they cannot even view', function () {
    $host = User::factory()->create();
    $stranger = eligibleCollaborator();
    $session = LiveSession::startStream($host, visibility: LiveSession::VISIBILITY_FOLLOWERS);

    expect(fn () => $session->requestCollaboration($stranger))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('the host can approve a pending request, and the collaborator can then publish', function () {
    Event::fake([StreamCollaborationUpdated::class]);

    $host = User::factory()->create();
    $viewer = eligibleCollaborator();
    $session = LiveSession::startStream($host);
    $session->requestCollaboration($viewer);

    expect($session->canPublish($viewer))->toBeFalse();

    $session->approveCollaborator($host, $viewer);

    expect($session->isApprovedCollaborator($viewer))->toBeTrue();
    expect($session->canPublish($viewer))->toBeTrue();

    Event::assertDispatched(StreamCollaborationUpdated::class, fn ($e) => $e->collaborator->is($viewer) && $e->status === 'approved');
});

test('only the host can approve a collaborator', function () {
    $host = User::factory()->create();
    $viewer = eligibleCollaborator();
    $someoneElse = User::factory()->create();
    $session = LiveSession::startStream($host);
    $session->requestCollaboration($viewer);

    expect(fn () => $session->approveCollaborator($someoneElse, $viewer))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('the host can deny a pending request, and remove an already-approved collaborator', function () {
    $host = User::factory()->create();
    $viewer = eligibleCollaborator();
    $session = LiveSession::startStream($host);
    $session->requestCollaboration($viewer);

    $session->removeCollaborator($host, $viewer);
    expect($session->hasRequestedCollaboration($viewer))->toBeFalse();

    $session->requestCollaboration($viewer);
    $session->approveCollaborator($host, $viewer);
    expect($session->canPublish($viewer))->toBeTrue();

    $session->removeCollaborator($host, $viewer);
    expect($session->canPublish($viewer))->toBeFalse();
});

test('an approved collaborator gets a publish-capable LiveKit token', function () {
    $host = User::factory()->create();
    $viewer = eligibleCollaborator();
    $session = LiveSession::create([
        'host_id' => $host->id,
        'room_name' => 'collab-token-test',
        'type' => LiveSession::TYPE_STREAM,
        'status' => LiveSession::STATUS_LIVE,
        'started_at' => now(),
    ]);
    $session->requestCollaboration($viewer);
    $session->approveCollaborator($host, $viewer);

    $token = LiveKitToken::generate($session, $viewer);

    expect($token)->toBeString();
});

test('the room page shows a request-to-collaborate button only to eligible, non-host viewers', function () {
    $host = User::factory()->create();
    $eligibleViewer = eligibleCollaborator();
    $ineligibleViewer = User::factory()->create();
    $session = LiveSession::create([
        'host_id' => $host->id,
        'room_name' => 'collab-button-test',
        'type' => LiveSession::TYPE_STREAM,
        'status' => LiveSession::STATUS_LIVE,
        'started_at' => now(),
    ]);

    Livewire::actingAs($eligibleViewer)
        ->test('pages::live.show', ['liveSession' => $session])
        ->assertSee('Request to collaborate');

    Livewire::actingAs($ineligibleViewer)
        ->test('pages::live.show', ['liveSession' => $session])
        ->assertDontSee('Request to collaborate');

    Livewire::actingAs($host)
        ->test('pages::live.show', ['liveSession' => $session])
        ->assertDontSee('Request to collaborate');
});

test('an eligible viewer can request to collaborate from the room page, and the host can approve it from there', function () {
    $host = User::factory()->create();
    $viewer = eligibleCollaborator();
    $session = LiveSession::create([
        'host_id' => $host->id,
        'room_name' => 'collab-flow-test',
        'type' => LiveSession::TYPE_STREAM,
        'status' => LiveSession::STATUS_LIVE,
        'started_at' => now(),
    ]);

    Livewire::actingAs($viewer)
        ->test('pages::live.show', ['liveSession' => $session])
        ->call('requestCollaboration')
        ->assertSee('Waiting for the host to approve');

    Livewire::actingAs($host)
        ->test('pages::live.show', ['liveSession' => $session])
        ->assertSee('wants to collaborate')
        ->call('approveCollaborator', $viewer->id)
        ->assertSee('is collaborating');

    expect($session->fresh()->isApprovedCollaborator($viewer))->toBeTrue();
});
