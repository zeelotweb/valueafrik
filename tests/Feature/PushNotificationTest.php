<?php

use App\Models\User;
use App\Notifications\NewFollower;
use NotificationChannels\WebPush\PushSubscription;

test('a user can register a push subscription', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('push-subscriptions.store'), [
        'endpoint' => 'https://push.example.com/endpoint-1',
        'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-token'],
    ]);

    $response->assertOk();

    expect(PushSubscription::where('endpoint', 'https://push.example.com/endpoint-1')->exists())->toBeTrue();
    expect($user->pushSubscriptions()->count())->toBe(1);
});

test('a user can remove a push subscription', function () {
    $user = User::factory()->create();
    $user->updatePushSubscription('https://push.example.com/endpoint-2', 'key', 'token');

    $response = $this->actingAs($user)->deleteJson(route('push-subscriptions.destroy'), [
        'endpoint' => 'https://push.example.com/endpoint-2',
    ]);

    $response->assertOk();
    expect($user->pushSubscriptions()->count())->toBe(0);
});

test('guests cannot register a push subscription', function () {
    $response = $this->postJson(route('push-subscriptions.store'), [
        'endpoint' => 'https://push.example.com/endpoint-3',
        'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-token'],
    ]);

    $response->assertUnauthorized();
});

test('every notification builds a web push payload from its existing toArray data', function () {
    $user = User::factory()->create();
    $actor = User::factory()->create(['name' => 'Actor Name']);

    $notification = new NewFollower($actor);

    $message = $notification->toWebPush($user, $notification);

    expect($message->toArray()['body'])->toContain('Actor Name');
    expect($message->toArray()['data']['url'])->toBe(route('profile.show', $actor));
});
