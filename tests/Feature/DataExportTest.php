<?php

use App\Models\BridgePost;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('a guest cannot download data export', function () {
    $this->get(route('data-export.download'))->assertRedirect(route('login'));
});

test('a user can download a JSON export of their own data', function () {
    $user = User::factory()->create(['name' => 'Amara Osei']);
    $user->wallPosts()->create(['body' => 'Hello wall.']);

    $response = $this->actingAs($user)->get(route('data-export.download'));

    $response->assertOk();
    $response->assertHeader('Content-Disposition');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');

    $data = $response->json();

    expect($data['account']['name'])->toBe('Amara Osei');
    expect($data['account']['email'])->toBe($user->email);
    expect($data['wall_posts'])->toHaveCount(1);
    expect($data['wall_posts'][0]['body'])->toBe('Hello wall.');
});

test('the export includes the full conversation transcript, not just this user\'s own messages', function () {
    // Unlike Bridge Posts (scoped to your own side), a message someone
    // else sent you is already addressed to you and visible to you in the
    // app regardless of who typed it — the export includes both directions.
    $user = User::factory()->create();
    $other = User::factory()->create(['name' => 'Yuki Tanaka']);
    $conversation = Conversation::between($user, $other);

    $conversation->messages()->create(['user_id' => $user->id, 'body' => 'My message']);
    $conversation->messages()->create(['user_id' => $other->id, 'body' => 'Their message']);

    $data = $this->actingAs($user)->get(route('data-export.download'))->json();

    expect($data['messages'])->toHaveCount(1);
    expect($data['messages'][0]['with'])->toBe('Yuki Tanaka');
    expect($data['messages'][0]['messages'])->toHaveCount(2);
    expect($data['messages'][0]['messages'][0])->toMatchArray(['from' => 'You', 'body' => 'My message']);
    expect($data['messages'][0]['messages'][1])->toMatchArray(['from' => 'Yuki Tanaka', 'body' => 'Their message']);
});

test('a message deleted for everyone exports as redacted, not its original body', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::between($user, $other);
    $message = $conversation->messages()->create(['user_id' => $user->id, 'body' => 'Oops, sensitive']);
    $message->deleteForEveryone($user);

    $data = $this->actingAs($user)->get(route('data-export.download'))->json();

    expect($data['messages'][0]['messages'][0]['body'])->toBeNull();
});

test('the export includes photos this user uploaded, tagged with where they were posted', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $post = $user->wallPosts()->create(['body' => 'Check this out']);
    $post->media()->create([
        'user_id' => $user->id, 'disk' => 'public', 'type' => 'image',
        'path' => 'wall-media/photo.webp', 'mime_type' => 'image/webp', 'size' => 100,
    ]);

    $data = $this->actingAs($user)->get(route('data-export.download'))->json();

    expect($data['media'])->toHaveCount(1);
    expect($data['media'][0]['context'])->toBe('wall_post');
    expect($data['media'][0]['url'])->toContain('wall-media/photo.webp');
});

test('the export does not include media someone else uploaded', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::between($user, $other);
    $message = $conversation->messages()->create(['user_id' => $other->id, 'body' => '']);
    $message->media()->create([
        'user_id' => $other->id, 'disk' => 'public', 'type' => 'image',
        'path' => 'message-media/photo.webp', 'mime_type' => 'image/webp', 'size' => 100,
    ]);

    $data = $this->actingAs($user)->get(route('data-export.download'))->json();

    expect($data['media'])->toHaveCount(0);
});

test('the export includes bridge posts on both the initiator and partner side', function () {
    $user = User::factory()->create();
    $partner = User::factory()->create(['name' => 'Diego Torres']);
    BridgePost::create([
        'theme' => 'Weddings',
        'initiator_id' => $user->id,
        'partner_id' => $partner->id,
        'status' => BridgePost::STATUS_ACTIVE,
        'initiator_body' => 'Here is how we do weddings.',
    ]);

    $data = $this->actingAs($user)->get(route('data-export.download'))->json();

    expect($data['bridge_posts'])->toHaveCount(1);
    expect($data['bridge_posts'][0]['role'])->toBe('initiator');
    expect($data['bridge_posts'][0]['other_participant'])->toBe('Diego Torres');
    expect($data['bridge_posts'][0]['your_side'])->toBe('Here is how we do weddings.');
});
