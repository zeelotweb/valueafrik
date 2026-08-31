<?php

use App\Models\Community;
use App\Models\Conversation;
use App\Models\Heritage;
use App\Models\User;
use Livewire\Livewire;

function communityFor(User $owner, array $attributes = []): Community
{
    $community = $owner->ownedCommunities()->create(array_merge([
        'name' => 'Test Community',
        'slug' => 'test-community-'.uniqid(),
        'visibility' => Community::VISIBILITY_PUBLIC,
        'participation_level' => Community::PARTICIPATION_POST,
    ], $attributes));

    $community->members()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

    return $community;
}

test('bridge score sums all events for a user', function () {
    $user = User::factory()->create();

    $user->awardBridgeScore('wall_post');
    $user->awardBridgeScore('community_post');

    expect($user->bridgeScore())->toBe(
        config('bridge_score.points.wall_post') + config('bridge_score.points.community_post')
    );
});

test('badge reflects the highest score threshold crossed', function () {
    config(['bridge_score.badges' => [10 => ['key' => 'a', 'name' => 'A'], 100 => ['key' => 'b', 'name' => 'B']]]);

    $user = User::factory()->create();

    expect($user->bridgeBadge())->toBeNull();

    $user->awardBridgeScore('wall_post');
    $user->bridgeScoreEvents()->update(['points' => 50]);
    expect($user->bridgeBadge()['key'])->toBe('a');

    $user->bridgeScoreEvents()->update(['points' => 150]);
    expect($user->bridgeBadge()['key'])->toBe('b');
});

test('following someone awards points to the follower and the followed', function () {
    $follower = User::factory()->create();
    $followed = User::factory()->create();

    Livewire::actingAs($follower)
        ->test('pages::profile.follow-button', ['user' => $followed])
        ->call('toggle');

    expect($follower->fresh()->bridgeScore())->toBe(config('bridge_score.points.follow'));
    expect($followed->fresh()->bridgeScore())->toBe(config('bridge_score.points.followed_by_someone'));
});

test('following across a heritage line awards a bonus', function () {
    $yoruba = Heritage::create(['name' => 'Yoruba', 'slug' => 'yoruba']);
    $irish = Heritage::create(['name' => 'Irish', 'slug' => 'irish']);

    $follower = User::factory()->create();
    $follower->heritages()->attach($yoruba->id);

    $followed = User::factory()->create();
    $followed->heritages()->attach($irish->id);

    Livewire::actingAs($follower)
        ->test('pages::profile.follow-button', ['user' => $followed])
        ->call('toggle');

    expect($follower->fresh()->bridgeScore())->toBe(
        config('bridge_score.points.follow') + config('bridge_score.points.follow_cross_heritage_bonus')
    );
});

test('following within the same heritage does not award the cross heritage bonus', function () {
    $yoruba = Heritage::create(['name' => 'Yoruba', 'slug' => 'yoruba']);

    $follower = User::factory()->create();
    $follower->heritages()->attach($yoruba->id);

    $followed = User::factory()->create();
    $followed->heritages()->attach($yoruba->id);

    Livewire::actingAs($follower)
        ->test('pages::profile.follow-button', ['user' => $followed])
        ->call('toggle');

    expect($follower->fresh()->bridgeScore())->toBe(config('bridge_score.points.follow'));
});

test('unfollowing does not deduct previously earned points', function () {
    $follower = User::factory()->create();
    $followed = User::factory()->create();

    $component = Livewire::actingAs($follower)
        ->test('pages::profile.follow-button', ['user' => $followed]);

    $component->call('toggle');
    $component->call('toggle');

    expect($follower->fresh()->bridgeScore())->toBe(config('bridge_score.points.follow'));
});

test('an incomplete roots save does not award the completion bonus', function () {
    $user = User::factory()->create();
    $heritage = Heritage::create(['name' => 'Yoruba', 'slug' => 'yoruba']);

    Livewire::actingAs($user)->test('pages::settings.roots')
        ->set('bio', 'Building bridges.')
        ->set('heritageIds', [$heritage->id])
        ->set('languageIds', [])
        ->call('save');

    expect($user->fresh()->bridgeScore())->toBe(0);
});

test('completing roots awards a one time bonus and does not double award', function () {
    $user = User::factory()->create();
    $heritage = Heritage::create(['name' => 'Yoruba', 'slug' => 'yoruba']);
    $language = \App\Models\Language::create(['name' => 'Yoruba', 'slug' => 'yoruba']);

    $component = Livewire::actingAs($user)->test('pages::settings.roots')
        ->set('bio', 'Building bridges.')
        ->set('heritageIds', [$heritage->id])
        ->set('languageIds', [$language->id])
        ->call('save');

    expect($user->fresh()->bridgeScore())->toBe(config('bridge_score.points.roots_completed'));

    $component->call('save');

    expect($user->fresh()->bridgeScore())->toBe(config('bridge_score.points.roots_completed'));
});

test('a wall post awards points', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::profile.wall-composer')
        ->set('body', 'hello')
        ->call('post');

    expect($user->fresh()->bridgeScore())->toBe(config('bridge_score.points.wall_post'));
});

test('joining a public community awards points immediately', function () {
    $owner = User::factory()->create();
    $community = communityFor($owner);
    $joiner = User::factory()->create();

    Livewire::actingAs($joiner)
        ->test('pages::communities.join-button', ['community' => $community])
        ->call('join');

    expect($joiner->fresh()->bridgeScore())->toBe(config('bridge_score.points.community_joined'));
});

test('a pending private community request does not award points until approved', function () {
    $owner = User::factory()->create();
    $community = communityFor($owner, ['visibility' => Community::VISIBILITY_PRIVATE]);
    $requester = User::factory()->create();

    Livewire::actingAs($requester)
        ->test('pages::communities.join-button', ['community' => $community])
        ->call('join');

    expect($requester->fresh()->bridgeScore())->toBe(0);

    Livewire::actingAs($owner)
        ->test('pages::communities.members', ['community' => $community])
        ->call('approve', $requester->id);

    expect($requester->fresh()->bridgeScore())->toBe(config('bridge_score.points.community_joined'));
});

test('posting in a community awards points', function () {
    $owner = User::factory()->create();
    $community = communityFor($owner);

    Livewire::actingAs($owner)
        ->test('pages::communities.composer', ['community' => $community])
        ->set('body', 'hello community')
        ->call('post');

    expect($owner->fresh()->bridgeScore())->toBe(config('bridge_score.points.community_post'));
});

test('being promoted to monitor awards points', function () {
    config(['communities.monitor_milestones' => [0 => 1]]);

    $owner = User::factory()->create();
    $community = communityFor($owner);
    $member = User::factory()->create();
    $community->members()->attach($member->id, ['role' => 'member', 'status' => 'active']);

    Livewire::actingAs($owner)
        ->test('pages::communities.members', ['community' => $community])
        ->call('promote', $member->id);

    expect($member->fresh()->bridgeScore())->toBe(config('bridge_score.points.promoted_to_monitor'));
});

test('starting a new conversation awards points but reopening an existing one does not', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    Livewire::actingAs($a)
        ->test('pages::profile.message-button', ['user' => $b])
        ->call('startConversation');

    expect($a->fresh()->bridgeScore())->toBe(config('bridge_score.points.conversation_started'));

    Livewire::actingAs($a)
        ->test('pages::profile.message-button', ['user' => $b])
        ->call('startConversation');

    expect($a->fresh()->bridgeScore())->toBe(config('bridge_score.points.conversation_started'));
    expect(Conversation::count())->toBe(1);
});
