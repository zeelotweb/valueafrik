<?php

use App\Models\BridgePost;
use App\Models\Conversation;
use App\Models\User;

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

test('the export only includes messages this user sent, not the other participant\'s', function () {
    $user = User::factory()->create();
    $other = User::factory()->create(['name' => 'Yuki Tanaka']);
    $conversation = Conversation::between($user, $other);

    $conversation->messages()->create(['user_id' => $user->id, 'body' => 'My message']);
    $conversation->messages()->create(['user_id' => $other->id, 'body' => 'Their message']);

    $data = $this->actingAs($user)->get(route('data-export.download'))->json();

    expect($data['messages_sent'])->toHaveCount(1);
    expect($data['messages_sent'][0]['body'])->toBe('My message');
    expect($data['messages_sent'][0]['to'])->toBe('Yuki Tanaka');
});

test('a message deleted for everyone exports as redacted, not its original body', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::between($user, $other);
    $message = $conversation->messages()->create(['user_id' => $user->id, 'body' => 'Oops, sensitive']);
    $message->deleteForEveryone($user);

    $data = $this->actingAs($user)->get(route('data-export.download'))->json();

    expect($data['messages_sent'][0]['body'])->toBeNull();
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
