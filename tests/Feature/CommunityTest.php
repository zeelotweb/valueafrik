<?php

use App\Models\Community;
use App\Models\CommunityReport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

function createCommunity(User $owner, array $attributes = []): Community
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

test('a user with no followers can only create one community', function () {
    $user = User::factory()->create();

    expect($user->communitySlotLimit())->toBe(1);
    expect($user->canCreateCommunity())->toBeTrue();

    createCommunity($user);

    expect($user->fresh()->canCreateCommunity())->toBeFalse();

    Livewire::actingAs($user)
        ->test('pages::communities.create')
        ->set('name', 'Second Community')
        ->call('create')
        ->assertForbidden();

    expect($user->ownedCommunities()->count())->toBe(1);
});

test('follower count unlocks more community slots', function () {
    config(['communities.creation_milestones' => [0 => 1, 3 => 2]]);

    $user = User::factory()->create();
    $followers = User::factory()->count(3)->create();

    $user->followers()->attach($followers->pluck('id'));

    expect($user->fresh()->communitySlotLimit())->toBe(2);
});

test('creating a community auto attaches the owner as owner role', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::communities.create')
        ->set('name', 'Lagos Creatives')
        ->set('visibility', 'public')
        ->set('participation_level', 'post')
        ->call('create')
        ->assertHasNoErrors();

    $community = Community::first();

    expect($community->name)->toBe('Lagos Creatives');
    expect($community->roleFor($user))->toBe('owner');
});

test('anyone can view and join a public community', function () {
    $owner = User::factory()->create();
    $community = createCommunity($owner, ['visibility' => Community::VISIBILITY_PUBLIC]);
    $joiner = User::factory()->create();

    Livewire::actingAs($joiner)->test('pages::communities.show', ['community' => $community])->assertOk();

    Livewire::actingAs($joiner)
        ->test('pages::communities.join-button', ['community' => $community])
        ->call('join');

    expect($community->fresh()->roleFor($joiner))->toBe('member');
});

test('leaving and rejoining the same public community only ever awards bridge score once', function () {
    // Regression guard: join() awarded community_joined unconditionally,
    // and leave() has no cooldown — isJoinableBy() goes straight back to
    // true the moment you leave, so join/leave/join/leave was a trivial,
    // self-serve way to farm unlimited points from one community.
    $owner = User::factory()->create();
    $community = createCommunity($owner, ['visibility' => Community::VISIBILITY_PUBLIC]);
    $joiner = User::factory()->create();

    Livewire::actingAs($joiner)->test('pages::communities.join-button', ['community' => $community])->call('join');
    expect($joiner->fresh()->bridgeScore())->toBe(config('bridge_score.points.community_joined'));

    Livewire::actingAs($joiner)->test('pages::communities.join-button', ['community' => $community])->call('leave');
    Livewire::actingAs($joiner)->test('pages::communities.join-button', ['community' => $community])->call('join');

    expect($joiner->fresh()->bridgeScore())->toBe(config('bridge_score.points.community_joined'));
    expect($community->fresh()->isMember($joiner))->toBeTrue();
});

test('approving the same private-community request twice only ever awards bridge score once', function () {
    $owner = User::factory()->create();
    $community = createCommunity($owner, ['visibility' => Community::VISIBILITY_PRIVATE]);
    $requester = User::factory()->create();
    $community->members()->attach($requester->id, ['role' => 'member', 'status' => 'pending']);

    Livewire::actingAs($owner)
        ->test('pages::communities.members', ['community' => $community])
        ->call('approve', $requester->id);

    expect($requester->fresh()->bridgeScore())->toBe(config('bridge_score.points.community_joined'));

    // Simulates approve() being reachable a second time for someone
    // already active (e.g. a stale request row) without a fresh query
    // guard — the reason+subject check should still hold the line.
    Livewire::actingAs($owner)
        ->test('pages::communities.members', ['community' => $community])
        ->call('approve', $requester->id);

    expect($requester->fresh()->bridgeScore())->toBe(config('bridge_score.points.community_joined'));
});

test('a private community is hidden from non members and requires approval to join', function () {
    $owner = User::factory()->create();
    $community = createCommunity($owner, ['visibility' => Community::VISIBILITY_PRIVATE]);
    $outsider = User::factory()->create();

    Livewire::actingAs($outsider)
        ->test('pages::communities.show', ['community' => $community])
        ->assertForbidden();

    Livewire::actingAs($outsider)
        ->test('pages::communities.join-button', ['community' => $community])
        ->call('join');

    $membership = $community->fresh()->membershipFor($outsider);
    expect($membership->status)->toBe('pending');
    expect($community->fresh()->isMember($outsider))->toBeFalse();

    Livewire::actingAs($owner)
        ->test('pages::communities.members', ['community' => $community])
        ->call('approve', $outsider->id);

    expect($community->fresh()->isMember($outsider))->toBeTrue();
});

test('a followers only community can only be joined by the owners followers', function () {
    $owner = User::factory()->create();
    $community = createCommunity($owner, ['visibility' => Community::VISIBILITY_FOLLOWERS_ONLY]);
    $stranger = User::factory()->create();
    $follower = User::factory()->create();

    $owner->followers()->attach($follower->id);

    Livewire::actingAs($stranger)
        ->test('pages::communities.join-button', ['community' => $community])
        ->call('join')
        ->assertStatus(422);

    Livewire::actingAs($follower)
        ->test('pages::communities.join-button', ['community' => $community])
        ->call('join');

    expect($community->fresh()->isMember($follower))->toBeTrue();
    expect($community->fresh()->isMember($stranger))->toBeFalse();
});

test('view only communities restrict posting to owners and monitors', function () {
    $owner = User::factory()->create();
    $community = createCommunity($owner, ['participation_level' => Community::PARTICIPATION_VIEW_ONLY]);
    $member = User::factory()->create();
    $community->members()->attach($member->id, ['role' => 'member', 'status' => 'active']);

    Livewire::actingAs($member)
        ->test('pages::communities.composer', ['community' => $community])
        ->set('body', 'trying to post')
        ->call('post')
        ->assertForbidden();

    expect($community->posts()->count())->toBe(0);

    Livewire::actingAs($owner)
        ->test('pages::communities.composer', ['community' => $community])
        ->set('body', 'owner announcement')
        ->call('post')
        ->assertHasNoErrors();

    expect($community->posts()->count())->toBe(1);
});

test('any active member can post when participation is open', function () {
    $owner = User::factory()->create();
    $community = createCommunity($owner);
    $member = User::factory()->create();
    $community->members()->attach($member->id, ['role' => 'member', 'status' => 'active']);

    Livewire::actingAs($member)
        ->test('pages::communities.composer', ['community' => $community])
        ->set('body', 'hello community')
        ->call('post')
        ->assertHasNoErrors();

    expect($community->posts()->count())->toBe(1);
});

test('a member can report a post and it logs for the community', function () {
    $owner = User::factory()->create();
    $community = createCommunity($owner);
    $author = User::factory()->create();
    $community->members()->attach($author->id, ['role' => 'member', 'status' => 'active']);
    $post = $community->posts()->create(['user_id' => $author->id, 'body' => 'offensive post']);

    $reporter = User::factory()->create();
    $community->members()->attach($reporter->id, ['role' => 'member', 'status' => 'active']);

    Livewire::actingAs($reporter)
        ->test('pages::communities.posts', ['community' => $community])
        ->call('startReport', $post->id)
        ->set('reportReason', 'This breaks community rules.')
        ->call('submitReport');

    $report = CommunityReport::first();

    expect($report)->not->toBeNull();
    expect($report->reporter_id)->toBe($reporter->id);
    expect($report->reportable_id)->toBe($post->id);
    expect($report->status)->toBe('open');
});

test('post author and moderators can delete a community post but other members cannot', function () {
    $owner = User::factory()->create();
    $community = createCommunity($owner);
    $author = User::factory()->create();
    $other = User::factory()->create();
    $community->members()->attach([$author->id, $other->id], ['role' => 'member', 'status' => 'active']);
    $post = $community->posts()->create(['user_id' => $author->id, 'body' => 'mine']);

    Livewire::actingAs($other)
        ->test('pages::communities.posts', ['community' => $community])
        ->call('delete', $post->id)
        ->assertForbidden();

    expect($community->posts()->count())->toBe(1);

    Livewire::actingAs($owner)
        ->test('pages::communities.posts', ['community' => $community])
        ->call('delete', $post->id);

    expect($community->posts()->count())->toBe(0);
});

test('owner can promote a member to monitor within the earned slot limit', function () {
    config(['communities.monitor_milestones' => [2 => 1]]);

    $owner = User::factory()->create();
    $community = createCommunity($owner);
    $memberA = User::factory()->create();
    $memberB = User::factory()->create();
    $community->members()->attach([$memberA->id, $memberB->id], ['role' => 'member', 'status' => 'active']);

    Livewire::actingAs($owner)
        ->test('pages::communities.members', ['community' => $community])
        ->call('promote', $memberA->id);

    expect($community->fresh()->roleFor($memberA))->toBe('monitor');

    // Slot limit is 1 (earned at 2 active members), already used — second promotion should be blocked.
    Livewire::actingAs($owner)
        ->test('pages::communities.members', ['community' => $community])
        ->call('promote', $memberB->id)
        ->assertStatus(422);

    expect($community->fresh()->roleFor($memberB))->toBe('member');
});

test('a monitor can dismiss a member from the community', function () {
    $owner = User::factory()->create();
    $community = createCommunity($owner);
    $monitor = User::factory()->create();
    $troublemaker = User::factory()->create();
    $community->members()->attach($monitor->id, ['role' => 'monitor', 'status' => 'active']);
    $community->members()->attach($troublemaker->id, ['role' => 'member', 'status' => 'active']);

    Livewire::actingAs($monitor)
        ->test('pages::communities.members', ['community' => $community])
        ->call('dismiss', $troublemaker->id);

    expect($community->fresh()->isMember($troublemaker))->toBeFalse();
});

test('the owner cannot leave their own community, even by calling leave() directly', function () {
    // Regression guard: the "Leave" button was already hidden for the owner
    // in the template, but leave() itself had no server-side check —
    // Livewire::test() (and a forged request) reaches it directly. Detaching
    // the owner from the pivot while communities.owner_id still points at
    // them would strand the community: canModerate()/canView() key off the
    // pivot role, not that column, and there's no ownership-transfer
    // feature to recover it afterward.
    $owner = User::factory()->create();
    $community = createCommunity($owner);

    Livewire::actingAs($owner)
        ->test('pages::communities.join-button', ['community' => $community])
        ->call('leave')
        ->assertForbidden();

    expect($community->fresh()->isMember($owner))->toBeTrue();
    expect($community->fresh()->owner_id)->toBe($owner->id);
});

test('a monitor cannot promote or demote anyone, including themselves', function () {
    $owner = User::factory()->create();
    $community = createCommunity($owner);
    $monitor = User::factory()->create();
    $member = User::factory()->create();
    $community->members()->attach($monitor->id, ['role' => 'monitor', 'status' => 'active']);
    $community->members()->attach($member->id, ['role' => 'member', 'status' => 'active']);

    Livewire::actingAs($monitor)
        ->test('pages::communities.members', ['community' => $community])
        ->call('promote', $member->id)
        ->assertForbidden();

    Livewire::actingAs($monitor)
        ->test('pages::communities.members', ['community' => $community])
        ->call('demote', $monitor->id)
        ->assertForbidden();

    expect($community->fresh()->roleFor($member))->toBe('member');
    expect($community->fresh()->roleFor($monitor))->toBe('monitor');
});

test('a plain member cannot dismiss another member or approve/reject a join request', function () {
    $owner = User::factory()->create();
    $community = createCommunity($owner, ['visibility' => Community::VISIBILITY_PRIVATE]);
    $member = User::factory()->create();
    $troublemaker = User::factory()->create();
    $requester = User::factory()->create();
    $community->members()->attach($member->id, ['role' => 'member', 'status' => 'active']);
    $community->members()->attach($troublemaker->id, ['role' => 'member', 'status' => 'active']);
    $community->members()->attach($requester->id, ['role' => 'member', 'status' => 'pending']);

    Livewire::actingAs($member)
        ->test('pages::communities.members', ['community' => $community])
        ->call('dismiss', $troublemaker->id)
        ->assertForbidden();

    Livewire::actingAs($member)
        ->test('pages::communities.members', ['community' => $community])
        ->call('approve', $requester->id)
        ->assertForbidden();

    Livewire::actingAs($member)
        ->test('pages::communities.members', ['community' => $community])
        ->call('reject', $requester->id)
        ->assertForbidden();

    expect($community->fresh()->isMember($troublemaker))->toBeTrue();
    expect($community->fresh()->membershipFor($requester)->status)->toBe('pending');
});

test('a community can carry a photo attachment on a post', function () {
    $owner = User::factory()->create();
    $community = createCommunity($owner);

    Livewire::actingAs($owner)
        ->test('pages::communities.composer', ['community' => $community])
        ->set('photos', [UploadedFile::fake()->image('photo.jpg')])
        ->call('post')
        ->assertHasNoErrors();

    $post = $community->posts()->first();

    expect($post->media)->toHaveCount(1);
    Storage::disk('public')->assertExists($post->media->first()->path);
});

test('a staged photo can be removed before posting to a community', function () {
    $owner = User::factory()->create();
    $community = createCommunity($owner);

    $component = Livewire::actingAs($owner)
        ->test('pages::communities.composer', ['community' => $community])
        ->set('photos', [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.jpg'),
        ])
        ->call('removePhoto', 0);

    expect($component->get('photos'))->toHaveCount(1);
    expect($component->get('photos')[0]->getClientOriginalName())->toBe('second.jpg');

    $component->call('post')->assertHasNoErrors();

    expect($community->posts()->first()->media)->toHaveCount(1);
});

// --- pagination -------------------------------------------------------------

test('community posts beyond the first page are reachable via loadMore', function () {
    $owner = User::factory()->create();
    $community = createCommunity($owner);

    foreach (range(1, 15) as $i) {
        $community->posts()->create(['user_id' => $owner->id, 'body' => "Post {$i}"]);
    }

    $component = Livewire::actingAs($owner)->test('pages::communities.posts', ['community' => $community]);

    expect($component->instance()->postsWindow->take(10))->toHaveCount(10);
    $component->assertSet('hasMore', true);

    $component->call('loadMore');

    expect($component->instance()->postsWindow->take($component->get('loaded')))->toHaveCount(15);
    $component->assertSet('hasMore', false);
});

test('community members beyond the first page are reachable via loadMore', function () {
    $owner = User::factory()->create();
    $community = createCommunity($owner);

    $members = User::factory()->count(25)->create();
    $community->members()->attach($members->pluck('id'), ['role' => 'member', 'status' => 'active']);

    $component = Livewire::actingAs($owner)->test('pages::communities.members', ['community' => $community]);

    // 20 loaded + the owner themself in the underlying query = 21 fetched,
    // but the owner is filtered out in the template, not the query.
    expect($component->instance()->activeMembersWindow->take(20))->toHaveCount(20);
    $component->assertSet('hasMore', true);

    $component->call('loadMore');

    expect($component->instance()->activeMembersWindow->take($component->get('loaded')))->toHaveCount(26);
    $component->assertSet('hasMore', false);
});
