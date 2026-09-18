<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

test('user can post text to their own wall', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::profile.wall-composer')
        ->set('body', 'Hello from the wall.')
        ->call('post')
        ->assertHasNoErrors();

    expect($user->wallPosts()->count())->toBe(1);
    expect($user->wallPosts()->first()->body)->toBe('Hello from the wall.');
});

test('a wall post defaults to public and can be created with a different visibility', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::profile.wall-composer')
        ->assertSet('visibility', 'public')
        ->set('body', 'Followers only, please.')
        ->set('visibility', 'followers_only')
        ->call('post')
        ->assertHasNoErrors();

    expect($user->wallPosts()->first()->visibility)->toBe('followers_only');
});

test('editing a wall post can change its visibility, and the composer loads the current one', function () {
    $user = User::factory()->create();
    $post = $user->wallPosts()->create(['body' => 'Was public.', 'visibility' => 'public']);

    Livewire::actingAs($user)
        ->test('pages::profile.wall-composer')
        ->dispatch('edit-wall-post', postId: $post->id)
        ->assertSet('visibility', 'public')
        ->set('visibility', 'private')
        ->call('post')
        ->assertHasNoErrors();

    expect($post->fresh()->visibility)->toBe('private');
});

test('user can attach photos to a wall post', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::profile.wall-composer')
        ->set('body', 'With a photo.')
        ->set('photos', [UploadedFile::fake()->image('photo.jpg')])
        ->call('post')
        ->assertHasNoErrors();

    $post = $user->wallPosts()->first();

    expect($post->media)->toHaveCount(1);
    Storage::disk('public')->assertExists($post->media->first()->path);
});

test('a photo up to the new 20MB cap is accepted', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::profile.wall-composer')
        ->set('photos', [UploadedFile::fake()->image('big.jpg')->size(15 * 1024)])
        ->call('post')
        ->assertHasNoErrors();

    expect($user->wallPosts()->first()->media)->toHaveCount(1);
});

test('a photo over the 20MB cap is still rejected', function () {
    // Rejected at upload time (Livewire's own temporary-upload rule, see
    // config/livewire.php) rather than by this component's own validate()
    // call — the property never ends up populated, so the error shows up
    // immediately on set(), not after call('post').
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::profile.wall-composer')
        ->set('photos', [UploadedFile::fake()->image('too-big.jpg')->size(21 * 1024)])
        ->assertHasErrors(['photos.0']);
});

test('a photo wider or taller than 6000px is rejected regardless of file size', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::profile.wall-composer')
        ->set('photos', [UploadedFile::fake()->image('huge-dimensions.jpg', 7000, 4000)])
        ->call('post')
        ->assertHasErrors(['photos.0']);
});

test('user can remove a staged photo before posting', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::profile.wall-composer')
        ->set('body', 'With two photos, then one.')
        ->set('photos', [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.jpg'),
        ])
        ->call('removePhoto', 0);

    expect($component->get('photos'))->toHaveCount(1);
    expect($component->get('photos')[0]->getClientOriginalName())->toBe('second.jpg');

    $component->call('post')->assertHasNoErrors();

    expect($user->wallPosts()->first()->media)->toHaveCount(1);
});

test('a wall post requires a body or a photo', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::profile.wall-composer')
        ->set('body', '')
        ->call('post')
        ->assertHasErrors(['body']);

    expect($user->wallPosts()->count())->toBe(0);
});

test('wall posts are listed on the profile', function () {
    $user = User::factory()->create();
    $user->wallPosts()->create(['body' => 'A visible post']);

    Livewire::actingAs($user)
        ->test('pages::profile.wall-posts', ['user' => $user])
        ->assertSee('A visible post');
});

test('a wall post photo is wrapped in a media-viewer trigger with all the post\'s photo URLs', function () {
    $user = User::factory()->create();
    $post = $user->wallPosts()->create(['body' => 'Photo post']);
    $post->media()->create(['user_id' => $user->id, 'disk' => 'public', 'path' => 'wall-media/one.jpg', 'mime_type' => 'image/jpeg', 'type' => 'image', 'size' => 100]);
    $post->media()->create(['user_id' => $user->id, 'disk' => 'public', 'path' => 'wall-media/two.jpg', 'mime_type' => 'image/jpeg', 'type' => 'image', 'size' => 100]);

    $html = Livewire::actingAs($user)
        ->test('pages::profile.wall-posts', ['user' => $user])
        ->html();

    expect($html)->toContain('media-viewer:show');
    expect($html)->toContain('wall-media/one.jpg');
    expect($html)->toContain('wall-media/two.jpg');
    // Each photo button carries the full gallery plus its own index, so the
    // lightbox can navigate between this post's photos.
    expect($html)->toContain('index: 0');
    expect($html)->toContain('index: 1');
});

test('rendering the wall post list does not run more queries as the post count grows', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        $user->wallPosts()->create(['body' => "Post {$i}"]);
    }

    \Illuminate\Support\Facades\DB::enableQueryLog();
    Livewire::actingAs($user)->test('pages::profile.wall-posts', ['user' => $user]);
    $tenPostQueries = count(\Illuminate\Support\Facades\DB::getQueryLog());
    \Illuminate\Support\Facades\DB::flushQueryLog();

    for ($i = 0; $i < 10; $i++) {
        $user->wallPosts()->create(['body' => "More post {$i}"]);
    }

    \Illuminate\Support\Facades\DB::flushQueryLog();
    Livewire::actingAs($user)->test('pages::profile.wall-posts', ['user' => $user]);
    $twentyPostQueries = count(\Illuminate\Support\Facades\DB::getQueryLog());
    \Illuminate\Support\Facades\DB::disableQueryLog();

    // Without eager-loaded counts, each nested reactions/comments/bookmark
    // component fires its own query per post — doubling the post count
    // would nearly double the query count. With batching, it shouldn't
    // move at all (pagination caps both renders at the same 10 shown).
    expect($twentyPostQueries)->toBe($tenPostQueries);
});

test('the wall loads 10 at a time and reports whether more exist', function () {
    $user = User::factory()->create();

    foreach (range(1, 15) as $i) {
        $user->wallPosts()->create(['body' => "Post {$i}"]);
    }

    $component = Livewire::actingAs($user)->test('pages::profile.wall-posts', ['user' => $user]);

    expect($component->instance()->postsWindow->take(10))->toHaveCount(10);
    $component->assertSet('hasMore', true);

    $component->call('loadMore');

    expect($component->instance()->postsWindow->take($component->get('loaded')))->toHaveCount(15);
    $component->assertSet('hasMore', false);
});

test('posting a new wall post resets the loaded window back to the first page', function () {
    $user = User::factory()->create();

    foreach (range(1, 15) as $i) {
        $user->wallPosts()->create(['body' => "Post {$i}"]);
    }

    $component = Livewire::actingAs($user)->test('pages::profile.wall-posts', ['user' => $user]);
    $component->call('loadMore')->assertSet('loaded', 20);

    $component->dispatch('wall-post-created');

    $component->assertSet('loaded', 10);
    expect($component->instance()->postsWindow->take(10))->toHaveCount(10);
});

test('owner can delete their own wall post and its media', function () {
    $user = User::factory()->create();
    $post = $user->wallPosts()->create(['body' => 'Delete me']);
    $media = $post->media()->create([
        'user_id' => $user->id,
        'disk' => 'public',
        'path' => UploadedFile::fake()->image('photo.jpg')->store('wall-media', 'public'),
        'type' => 'image',
    ]);

    Livewire::actingAs($user)
        ->test('pages::profile.wall-posts', ['user' => $user])
        ->call('delete', $post->id);

    expect($user->wallPosts()->count())->toBe(0);
    Storage::disk('public')->assertMissing($media->path);
});

test('a user cannot delete another users wall post', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $post = $owner->wallPosts()->create(['body' => 'Not yours']);

    Livewire::actingAs($intruder)
        ->test('pages::profile.wall-posts', ['user' => $owner])
        ->call('delete', $post->id)
        ->assertForbidden();

    expect($owner->wallPosts()->count())->toBe(1);
});

// --- post options: hide / mute / block / report ----------------------------

test('hiding a post removes it from the viewers own list, not from the author\'s', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Hide me']);

    Livewire::actingAs($viewer)
        ->test('pages::profile.wall-posts', ['user' => $author])
        ->call('hidePost', $post->id)
        ->assertDontSee('Hide me');

    expect($post->isHiddenFor($viewer))->toBeTrue();
    expect($post->isHiddenFor($author))->toBeFalse();

    Livewire::actingAs($author)
        ->test('pages::profile.wall-posts', ['user' => $author])
        ->assertSee('Hide me');
});

test('hiding the same post twice does not error', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Hide me']);

    $post->hideFor($viewer);
    $post->hideFor($viewer);

    expect($post->hides()->where('user_id', $viewer->id)->count())->toBe(1);
});

test('muting a wall post author removes their posts from the dashboard feed but not their own wall', function () {
    $author = User::factory()->create(['name' => 'Muted Author']);
    $viewer = User::factory()->create();
    $viewer->following()->attach($author->id);
    $author->wallPosts()->create(['body' => 'Should disappear from the feed']);

    $viewer->mute($author);

    Livewire::actingAs($viewer)
        ->test('pages::dashboard.following-activity')
        ->assertDontSee('Should disappear from the feed');

    Livewire::actingAs($viewer)
        ->test('pages::profile.wall-posts', ['user' => $author])
        ->assertSee('Should disappear from the feed');
});

test('toggling mute from a post flips the state back and forth', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Whatever']);

    $component = Livewire::actingAs($viewer)->test('pages::profile.wall-posts', ['user' => $author]);

    $component->call('toggleMute', $post->id);
    expect($viewer->fresh()->hasMuted($author))->toBeTrue();

    $component->call('toggleMute', $post->id);
    expect($viewer->fresh()->hasMuted($author))->toBeFalse();
});

test('a user cannot mute themselves from their own post', function () {
    $user = User::factory()->create();
    $post = $user->wallPosts()->create(['body' => 'Mine']);

    Livewire::actingAs($user)
        ->test('pages::profile.wall-posts', ['user' => $user])
        ->call('toggleMute', $post->id)
        ->assertForbidden();
});

test('blocking from a post uses the same block relationship as the profile block button', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Block me']);

    Livewire::actingAs($viewer)
        ->test('pages::profile.wall-posts', ['user' => $author])
        ->call('toggleBlock', $post->id);

    expect($viewer->fresh()->hasBlocked($author))->toBeTrue();

    Livewire::actingAs($viewer)
        ->test('pages::profile.wall-posts', ['user' => $author])
        ->call('toggleBlock', $post->id);

    expect($viewer->fresh()->hasBlocked($author))->toBeFalse();
});

test('a wall post can be reported with no community attached', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Report me']);

    Livewire::actingAs($viewer)
        ->test('pages::profile.wall-posts', ['user' => $author])
        ->call('startReport', $post->id)
        ->set('reportReason', 'This is spam.')
        ->call('submitReport')
        ->assertHasNoErrors();

    $report = \App\Models\CommunityReport::first();

    expect($report)->not->toBeNull();
    expect($report->community_id)->toBeNull();
    expect($report->reason)->toBe('This is spam.');
    expect($report->reportable_id)->toBe($post->id);
});

test('reporting the same post twice from the same reporter does not create a duplicate open report', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Report me repeatedly']);

    $component = Livewire::actingAs($viewer)->test('pages::profile.wall-posts', ['user' => $author]);

    foreach (range(1, 5) as $i) {
        $component
            ->call('startReport', $post->id)
            ->set('reportReason', 'This is spam.')
            ->call('submitReport');
    }

    expect(\App\Models\CommunityReport::count())->toBe(1);
});
