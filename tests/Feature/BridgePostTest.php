<?php

use App\Models\BridgePost;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

test('a user can invite someone to a bridge post', function () {
    $initiator = User::factory()->create();
    $partner = User::factory()->create(['name' => 'Yuki Tanaka']);

    Livewire::actingAs($initiator)
        ->test('pages::profile.bridge-post-composer')
        ->set('theme', 'Weddings')
        ->set('partnerSearch', 'Yuki')
        ->call('pickPartner', $partner->id, 'Yuki Tanaka')
        ->call('send')
        ->assertHasNoErrors();

    $bridgePost = BridgePost::first();

    expect($bridgePost->theme)->toBe('Weddings');
    expect($bridgePost->initiator_id)->toBe($initiator->id);
    expect($bridgePost->partner_id)->toBe($partner->id);
    expect($bridgePost->status)->toBe(BridgePost::STATUS_PENDING);
});

test('a user cannot invite themselves', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::profile.bridge-post-composer')
        ->set('theme', 'Weddings')
        ->set('partnerId', $user->id)
        ->call('send')
        ->assertForbidden();

    expect(BridgePost::count())->toBe(0);
});

test('partner search excludes the searcher and requires at least two characters', function () {
    $searcher = User::factory()->create(['name' => 'Amara Osei']);
    $match = User::factory()->create(['name' => 'Amara Two']);

    $component = Livewire::actingAs($searcher)->test('pages::profile.bridge-post-composer');

    $component->set('partnerSearch', 'A');
    expect($component->get('results'))->toHaveCount(0);

    $component->set('partnerSearch', 'Amara');
    $results = $component->get('results');
    expect($results->pluck('id'))->not->toContain($searcher->id);
    expect($results->pluck('id'))->toContain($match->id);
});

test('a pending bridge post does not appear on either wall', function () {
    $initiator = User::factory()->create();
    $partner = User::factory()->create();
    $bridgePost = BridgePost::create([
        'theme' => 'Weddings',
        'initiator_id' => $initiator->id,
        'partner_id' => $partner->id,
        'status' => BridgePost::STATUS_PENDING,
    ]);

    Livewire::actingAs($initiator)
        ->test('pages::profile.bridge-posts', ['user' => $initiator])
        ->assertDontSee('Weddings');
});

test('the partner can accept or decline an invite but no one else can', function () {
    $initiator = User::factory()->create();
    $partner = User::factory()->create();
    $intruder = User::factory()->create();
    $bridgePost = BridgePost::create([
        'theme' => 'Weddings',
        'initiator_id' => $initiator->id,
        'partner_id' => $partner->id,
        'status' => BridgePost::STATUS_PENDING,
    ]);

    Livewire::actingAs($intruder)
        ->test('pages::dashboard.bridge-post-invites')
        ->call('accept', $bridgePost->id)
        ->assertForbidden();

    Livewire::actingAs($partner)
        ->test('pages::dashboard.bridge-post-invites')
        ->assertSee('Weddings')
        ->call('accept', $bridgePost->id);

    expect($bridgePost->fresh()->status)->toBe(BridgePost::STATUS_ACTIVE);
});

test('declining an invite marks it declined and it never appears on a wall', function () {
    $initiator = User::factory()->create();
    $partner = User::factory()->create();
    $bridgePost = BridgePost::create([
        'theme' => 'Weddings',
        'initiator_id' => $initiator->id,
        'partner_id' => $partner->id,
        'status' => BridgePost::STATUS_PENDING,
    ]);

    Livewire::actingAs($partner)
        ->test('pages::dashboard.bridge-post-invites')
        ->call('decline', $bridgePost->id);

    expect($bridgePost->fresh()->status)->toBe(BridgePost::STATUS_DECLINED);

    Livewire::actingAs($initiator)
        ->test('pages::profile.bridge-posts', ['user' => $initiator])
        ->assertDontSee('Weddings');
});

test('once active, the wall shows waiting for the side not yet written', function () {
    $initiator = User::factory()->create(['name' => 'Diego Torres']);
    $partner = User::factory()->create(['name' => 'Yuki Tanaka']);
    BridgePost::create([
        'theme' => 'Weddings',
        'initiator_id' => $initiator->id,
        'partner_id' => $partner->id,
        'status' => BridgePost::STATUS_ACTIVE,
    ]);

    Livewire::actingAs($initiator)
        ->test('pages::profile.bridge-posts', ['user' => $initiator])
        ->assertSee('Weddings')
        ->assertSee('Waiting for Yuki Tanaka to add their side.');
});

test('each participant can only submit their own side', function () {
    $initiator = User::factory()->create();
    $partner = User::factory()->create();
    $intruder = User::factory()->create();
    $bridgePost = BridgePost::create([
        'theme' => 'Weddings',
        'initiator_id' => $initiator->id,
        'partner_id' => $partner->id,
        'status' => BridgePost::STATUS_ACTIVE,
    ]);

    Livewire::actingAs($intruder)
        ->test('pages::profile.bridge-posts', ['user' => $initiator])
        ->call('startSide', $bridgePost->id)
        ->set('sideBody', 'sneaky')
        ->call('submitSide')
        ->assertForbidden();

    expect($bridgePost->fresh()->initiator_body)->toBeNull();
});

test('bridge score is only awarded once both sides are complete, to both participants', function () {
    $initiator = User::factory()->create();
    $partner = User::factory()->create();
    $bridgePost = BridgePost::create([
        'theme' => 'Weddings',
        'initiator_id' => $initiator->id,
        'partner_id' => $partner->id,
        'status' => BridgePost::STATUS_ACTIVE,
    ]);

    Livewire::actingAs($initiator)
        ->test('pages::profile.bridge-posts', ['user' => $initiator])
        ->call('startSide', $bridgePost->id)
        ->set('sideBody', 'Here is how we do weddings...')
        ->call('submitSide');

    expect($initiator->fresh()->bridgeScore())->toBe(0);
    expect($partner->fresh()->bridgeScore())->toBe(0);

    Livewire::actingAs($partner)
        ->test('pages::profile.bridge-posts', ['user' => $initiator])
        ->call('startSide', $bridgePost->id)
        ->set('sideBody', 'And here is how we do it...')
        ->call('submitSide');

    expect($initiator->fresh()->bridgeScore())->toBe(config('bridge_score.points.bridge_post_completed'));
    expect($partner->fresh()->bridgeScore())->toBe(config('bridge_score.points.bridge_post_completed'));
});

test('re-submitting a side after the bridge post is already complete does not re-award bridge score', function () {
    // Regression guard: isComplete() just checks both bodies are
    // non-empty, which stays true forever once reached — either side
    // re-submitting afterward (e.g. to add a photo) used to re-trigger
    // the completion award (and notification) every single time.
    $initiator = User::factory()->create();
    $partner = User::factory()->create();
    $bridgePost = BridgePost::create([
        'theme' => 'Weddings',
        'initiator_id' => $initiator->id,
        'partner_id' => $partner->id,
        'status' => BridgePost::STATUS_ACTIVE,
    ]);

    Livewire::actingAs($initiator)
        ->test('pages::profile.bridge-posts', ['user' => $initiator])
        ->call('startSide', $bridgePost->id)
        ->set('sideBody', 'Here is how we do weddings...')
        ->call('submitSide');

    Livewire::actingAs($partner)
        ->test('pages::profile.bridge-posts', ['user' => $initiator])
        ->call('startSide', $bridgePost->id)
        ->set('sideBody', 'And here is how we do it...')
        ->call('submitSide');

    expect($initiator->fresh()->bridgeScore())->toBe(config('bridge_score.points.bridge_post_completed'));

    // Re-submit the already-complete side again.
    Livewire::actingAs($initiator)
        ->test('pages::profile.bridge-posts', ['user' => $initiator])
        ->call('startSide', $bridgePost->id)
        ->set('sideBody', 'Here is how we do weddings, updated...')
        ->call('submitSide');

    expect($initiator->fresh()->bridgeScore())->toBe(config('bridge_score.points.bridge_post_completed'));
    expect($partner->fresh()->bridgeScore())->toBe(config('bridge_score.points.bridge_post_completed'));
});

test('a side can carry a photo attachment', function () {
    $initiator = User::factory()->create();
    $partner = User::factory()->create();
    $bridgePost = BridgePost::create([
        'theme' => 'Weddings',
        'initiator_id' => $initiator->id,
        'partner_id' => $partner->id,
        'status' => BridgePost::STATUS_ACTIVE,
    ]);

    Livewire::actingAs($initiator)
        ->test('pages::profile.bridge-posts', ['user' => $initiator])
        ->call('startSide', $bridgePost->id)
        ->set('sideBody', 'Here is how we do weddings...')
        ->set('sidePhotos', [UploadedFile::fake()->image('wedding.jpg')])
        ->call('submitSide');

    $media = $bridgePost->fresh()->media;
    expect($media)->toHaveCount(1);
    expect($media->first()->user_id)->toBe($initiator->id);
    Storage::disk('public')->assertExists($media->first()->path);
});

test('a staged side photo can be removed before submitting', function () {
    $initiator = User::factory()->create();
    $partner = User::factory()->create();
    $bridgePost = BridgePost::create([
        'theme' => 'Weddings',
        'initiator_id' => $initiator->id,
        'partner_id' => $partner->id,
        'status' => BridgePost::STATUS_ACTIVE,
    ]);

    $component = Livewire::actingAs($initiator)
        ->test('pages::profile.bridge-posts', ['user' => $initiator])
        ->call('startSide', $bridgePost->id)
        ->set('sideBody', 'Here is how we do weddings...')
        ->set('sidePhotos', [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.jpg'),
        ])
        ->call('removeSidePhoto', 0);

    expect($component->get('sidePhotos'))->toHaveCount(1);
    expect($component->get('sidePhotos')[0]->getClientOriginalName())->toBe('second.jpg');

    $component->call('submitSide');

    expect($bridgePost->fresh()->media)->toHaveCount(1);
});

// --- pagination -------------------------------------------------------------

test('bridge posts beyond the first page are reachable via loadMore, not silently dropped', function () {
    // Regression guard: this list used to be a flat ->limit(20)->get() with
    // no pagination at all — a 21st active bridge post was permanently
    // invisible, not just deferred to a later page.
    $user = User::factory()->create();

    foreach (range(1, 25) as $i) {
        $partner = User::factory()->create();
        BridgePost::create([
            'theme' => "Theme {$i}",
            'initiator_id' => $user->id,
            'partner_id' => $partner->id,
            'status' => BridgePost::STATUS_ACTIVE,
        ]);
    }

    $component = Livewire::actingAs($user)->test('pages::profile.bridge-posts', ['user' => $user]);

    expect($component->instance()->bridgePostsWindow->take(20))->toHaveCount(20);
    $component->assertSet('hasMore', true);

    $component->call('loadMore');

    expect($component->instance()->bridgePostsWindow->take($component->get('loaded')))->toHaveCount(25);
    $component->assertSet('hasMore', false);
});
