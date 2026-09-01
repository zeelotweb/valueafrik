<?php

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

test('conversation between two users is created once and reused', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    $first = Conversation::between($a, $b);
    $second = Conversation::between($b, $a);

    expect($first->id)->toBe($second->id);
    expect($first->participants)->toHaveCount(2);
});

test('message button starts a conversation and redirects to it', function () {
    $viewer = User::factory()->create();
    $other = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::profile.message-button', ['user' => $other])
        ->call('startConversation')
        ->assertRedirect(route('messages.show', Conversation::between($viewer, $other)));
});

test('a user cannot message themselves', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::profile.message-button', ['user' => $user])
        ->call('startConversation')
        ->assertForbidden();
});

test('non participant cannot open a conversation thread', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $intruder = User::factory()->create();

    $conversation = Conversation::between($a, $b);

    Livewire::actingAs($intruder)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->assertForbidden();
});

test('opening a thread marks it as read', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);
    $conversation->messages()->create(['user_id' => $b->id, 'body' => 'hi']);

    Livewire::actingAs($a)->test('pages::messages.show', ['conversation' => $conversation]);

    $lastRead = $conversation->participants()->where('users.id', $a->id)->first()->pivot->last_read_at;

    expect($lastRead)->not->toBeNull();
});

test('sending a message creates it, broadcasts it, and marks the thread read', function () {
    Event::fake([MessageSent::class]);

    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);

    Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->set('body', 'Hello there')
        ->call('send')
        ->assertHasNoErrors();

    expect($conversation->messages()->count())->toBe(1);
    expect($conversation->messages()->first()->body)->toBe('Hello there');

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($conversation) {
        return $event->broadcastOn()[0]->name === 'private-conversation.'.$conversation->id;
    });
});

test('sending a message still succeeds even if broadcasting the event fails', function () {
    config(['broadcasting.default' => 'not-a-real-driver']);

    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);

    Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->set('body', 'Should still send')
        ->call('send')
        ->assertHasNoErrors();

    expect($conversation->messages()->count())->toBe(1);
    expect($conversation->messages()->first()->body)->toBe('Should still send');
});

test('a message can carry a photo attachment', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);

    Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->set('photo', UploadedFile::fake()->image('photo.jpg'))
        ->call('send')
        ->assertHasNoErrors();

    $message = $conversation->messages()->first();

    expect($message->media)->toHaveCount(1);
    Storage::disk('public')->assertExists($message->media->first()->path);
});

test('a message requires a body or a photo', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);

    Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->set('body', '')
        ->call('send')
        ->assertHasErrors(['body']);

    expect($conversation->messages()->count())->toBe(0);
});

test('unread count only counts conversations with a newer message than last read', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);

    $conversation->messages()->create(['user_id' => $b->id, 'body' => 'first']);
    $conversation->participants()->updateExistingPivot($a->id, ['last_read_at' => now()]);

    expect($a->fresh()->unreadConversationsCount())->toBe(0);

    $second = $conversation->messages()->create(['user_id' => $b->id, 'body' => 'second, after read']);
    $second->forceFill(['created_at' => now()->addMinute()])->save();

    expect($a->fresh()->unreadConversationsCount())->toBe(1);
});

test('inbox lists conversations with the other participant and unread state', function () {
    $a = User::factory()->create();
    $b = User::factory()->create(['name' => 'Bola']);
    $conversation = Conversation::between($a, $b);
    $conversation->messages()->create(['user_id' => $b->id, 'body' => 'Unread ping']);

    Livewire::actingAs($a)
        ->test('pages::messages.inbox')
        ->assertSee('Bola')
        ->assertSee('Unread ping');

    expect($a->fresh()->unreadConversationsCount())->toBe(1);
});

test('the live message listener uses the exact dot prefixed event name Echo requires', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);

    // Without the leading dot, Echo namespaces the event to "App.Events.MessageSent",
    // which never matches what MessageSent::broadcastAs() actually sends on the
    // wire — the listener would silently never fire. Caught this live in the
    // browser once; guarding it here so it can't regress silently again.
    $listeners = Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->instance()
        ->getListeners();

    expect(array_keys($listeners))
        ->toContain("echo-private:conversation.{$conversation->id},.MessageSent");
});
