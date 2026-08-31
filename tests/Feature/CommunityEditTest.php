<?php

use App\Models\Community;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

function editableCommunity(User $owner, array $attributes = []): Community
{
    $community = $owner->ownedCommunities()->create(array_merge([
        'name' => 'Original Name',
        'slug' => 'original-name-'.uniqid(),
        'visibility' => Community::VISIBILITY_PUBLIC,
        'participation_level' => Community::PARTICIPATION_POST,
    ], $attributes));

    $community->members()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

    return $community;
}

test('the owner can update community settings', function () {
    $owner = User::factory()->create();
    $community = editableCommunity($owner);

    Livewire::actingAs($owner)
        ->test('pages::communities.edit', ['community' => $community])
        ->set('name', 'Renamed Community')
        ->set('description', 'A new description.')
        ->set('visibility', 'followers_only')
        ->set('participation_level', 'view_only')
        ->call('save')
        ->assertHasNoErrors();

    $community->refresh();

    expect($community->name)->toBe('Renamed Community');
    expect($community->description)->toBe('A new description.');
    expect($community->visibility)->toBe('followers_only');
    expect($community->participation_level)->toBe('view_only');
});

test('a non owner cannot access the edit page', function () {
    $owner = User::factory()->create();
    $community = editableCommunity($owner);
    $intruder = User::factory()->create();

    Livewire::actingAs($intruder)
        ->test('pages::communities.edit', ['community' => $community])
        ->assertForbidden();
});

test('switching away from private auto approves pending requests', function () {
    $owner = User::factory()->create();
    $community = editableCommunity($owner, ['visibility' => Community::VISIBILITY_PRIVATE]);
    $requester = User::factory()->create();
    $community->members()->attach($requester->id, ['role' => 'member', 'status' => 'pending']);

    Livewire::actingAs($owner)
        ->test('pages::communities.edit', ['community' => $community])
        ->set('name', $community->name)
        ->set('visibility', 'public')
        ->set('participation_level', $community->participation_level)
        ->call('save');

    expect($community->fresh()->isMember($requester))->toBeTrue();
    expect($requester->fresh()->bridgeScore())->toBe(config('bridge_score.points.community_joined'));
});

test('staying private does not touch pending requests', function () {
    $owner = User::factory()->create();
    $community = editableCommunity($owner, ['visibility' => Community::VISIBILITY_PRIVATE]);
    $requester = User::factory()->create();
    $community->members()->attach($requester->id, ['role' => 'member', 'status' => 'pending']);

    Livewire::actingAs($owner)
        ->test('pages::communities.edit', ['community' => $community])
        ->set('name', 'Still Private But Renamed')
        ->set('visibility', 'private')
        ->set('participation_level', $community->participation_level)
        ->call('save');

    expect($community->fresh()->isMember($requester))->toBeFalse();
    expect($community->fresh()->hasPendingRequestFrom($requester))->toBeTrue();
});

test('only the owner can upload a community avatar', function () {
    $owner = User::factory()->create();
    $community = editableCommunity($owner);
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->postJson(route('communities.avatar', $community), [
            'photo' => UploadedFile::fake()->image('avatar.jpg'),
        ])
        ->assertForbidden();

    $response = $this->actingAs($owner)
        ->postJson(route('communities.avatar', $community), [
            'photo' => UploadedFile::fake()->image('avatar.jpg', 400, 400),
        ]);

    $response->assertOk()->assertJsonStructure(['url']);

    $path = $community->fresh()->avatar_path;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

test('re-uploading a community cover deletes the previous file', function () {
    $owner = User::factory()->create();
    $community = editableCommunity($owner);

    $this->actingAs($owner)->postJson(route('communities.cover', $community), [
        'photo' => UploadedFile::fake()->image('first.jpg'),
    ]);
    $firstPath = $community->fresh()->cover_path;

    $this->actingAs($owner)->postJson(route('communities.cover', $community), [
        'photo' => UploadedFile::fake()->image('second.jpg'),
    ]);
    $secondPath = $community->fresh()->cover_path;

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);
});
