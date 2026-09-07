<?php

use App\Models\BridgePost;
use App\Models\Community;
use App\Models\CommunityPost;
use App\Models\LiveSession;
use App\Models\User;
use App\Models\WallPost;
use Livewire\Livewire;

test('a viewer following no one sees a prompt to discover people', function () {
    $viewer = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::dashboard.following-activity')
        ->assertSee("You're not following anyone yet")
        ->assertSeeHtml(route('discover.index'));
});

test('a viewer following someone with no activity sees an empty state instead of the discover prompt', function () {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();
    $viewer->following()->attach($followed->id);

    Livewire::actingAs($viewer)
        ->test('pages::dashboard.following-activity')
        ->assertSee('No recent activity from people you follow yet.')
        ->assertDontSee("You're not following anyone yet");
});

test('a wall post from someone followed appears in the feed', function () {
    $viewer = User::factory()->create();
    $followed = User::factory()->create(['name' => 'Followed Poster']);
    $viewer->following()->attach($followed->id);

    WallPost::create(['user_id' => $followed->id, 'body' => 'Bridge-building in progress.']);

    Livewire::actingAs($viewer)
        ->test('pages::dashboard.following-activity')
        ->assertSee('Followed Poster')
        ->assertSee('Bridge-building in progress.');
});

test('a wall post from someone not followed does not appear', function () {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();
    $viewer->following()->attach($followed->id);

    $stranger = User::factory()->create(['name' => 'Random Stranger']);
    WallPost::create(['user_id' => $stranger->id, 'body' => 'Should not show up.']);

    Livewire::actingAs($viewer)
        ->test('pages::dashboard.following-activity')
        ->assertDontSee('Random Stranger')
        ->assertDontSee('Should not show up.');
});

test('a community post from someone followed appears when the community is visible to the viewer', function () {
    $viewer = User::factory()->create();
    $followed = User::factory()->create(['name' => 'Community Poster']);
    $viewer->following()->attach($followed->id);

    $community = Community::create([
        'owner_id' => $followed->id,
        'name' => 'Diaspora Chefs',
        'slug' => 'diaspora-chefs-'.uniqid(),
        'visibility' => Community::VISIBILITY_PUBLIC,
        'participation_level' => Community::PARTICIPATION_POST,
    ]);

    CommunityPost::create(['community_id' => $community->id, 'user_id' => $followed->id, 'body' => 'New recipe drop.']);

    Livewire::actingAs($viewer)
        ->test('pages::dashboard.following-activity')
        ->assertSee('Community Poster')
        ->assertSee('Diaspora Chefs')
        ->assertSee('New recipe drop.');
});

test('a community post from someone followed is hidden when the community is private and the viewer cannot see it', function () {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();
    $viewer->following()->attach($followed->id);

    $community = Community::create([
        'owner_id' => $followed->id,
        'name' => 'Secret Circle',
        'slug' => 'secret-circle-'.uniqid(),
        'visibility' => Community::VISIBILITY_PRIVATE,
        'participation_level' => Community::PARTICIPATION_POST,
    ]);

    CommunityPost::create(['community_id' => $community->id, 'user_id' => $followed->id, 'body' => 'Members only chatter.']);

    Livewire::actingAs($viewer)
        ->test('pages::dashboard.following-activity')
        ->assertDontSee('Secret Circle')
        ->assertDontSee('Members only chatter.');
});

test('an active bridge post involving someone followed appears, regardless of which side they are on', function () {
    $viewer = User::factory()->create();
    $followedInitiator = User::factory()->create(['name' => 'Followed Initiator']);
    $followedPartner = User::factory()->create(['name' => 'Followed Partner']);
    $viewer->following()->attach([$followedInitiator->id, $followedPartner->id]);

    $stranger = User::factory()->create();

    BridgePost::create([
        'theme' => 'Wedding Traditions',
        'initiator_id' => $followedInitiator->id,
        'partner_id' => $stranger->id,
        'status' => BridgePost::STATUS_ACTIVE,
        'initiator_body' => 'Our side.',
        'partner_body' => 'Their side.',
    ]);

    Livewire::actingAs($viewer)
        ->test('pages::dashboard.following-activity')
        ->assertSee('Followed Initiator')
        ->assertSee('Wedding Traditions');
});

test('a pending bridge post without both sides contributed does not appear', function () {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();
    $viewer->following()->attach($followed->id);

    BridgePost::create([
        'theme' => 'Not Ready Yet',
        'initiator_id' => $followed->id,
        'partner_id' => User::factory()->create()->id,
        'status' => BridgePost::STATUS_PENDING,
    ]);

    Livewire::actingAs($viewer)
        ->test('pages::dashboard.following-activity')
        ->assertDontSee('Not Ready Yet');
});

test('a live stream from someone followed is pinned above the regular activity', function () {
    $viewer = User::factory()->create();
    $followed = User::factory()->create(['name' => 'Streaming Friend']);
    $viewer->following()->attach($followed->id);

    LiveSession::create([
        'host_id' => $followed->id,
        'room_name' => 'following-live-'.uniqid(),
        'type' => LiveSession::TYPE_STREAM,
        'status' => LiveSession::STATUS_LIVE,
        'started_at' => now(),
    ]);

    Livewire::actingAs($viewer)
        ->test('pages::dashboard.following-activity')
        ->assertSee('Streaming Friend')
        ->assertSee('Live');
});

test('an ended stream from someone followed does not show as live', function () {
    $viewer = User::factory()->create();
    $followed = User::factory()->create(['name' => 'Past Streamer']);
    $viewer->following()->attach($followed->id);

    LiveSession::create([
        'host_id' => $followed->id,
        'room_name' => 'following-ended-'.uniqid(),
        'type' => LiveSession::TYPE_STREAM,
        'status' => LiveSession::STATUS_ENDED,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
    ]);

    Livewire::actingAs($viewer)
        ->test('pages::dashboard.following-activity')
        ->assertDontSee('Past Streamer');
});
