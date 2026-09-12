<?php

use App\Events\MessageDeletedForEveryone;
use App\Events\MessageSent;
use App\Events\MessagesRead;
use App\Events\UserTyping;
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

test('a message with a photo broadcasts thumbnail_url, so the recipient\'s live-updating thread does not crash', function () {
    // Regression guard: MessageSent::broadcastWith() used to build its own
    // hand-maintained 'media' shape (url only), separate from
    // formatMessage()'s (url + thumbnail_url). They drifted — the
    // recipient's onMessageReceived() pushes the raw broadcast payload
    // straight into $this->messages with no reshaping, so a missing
    // thumbnail_url there crashed the Blade template the instant a media
    // message arrived live. Both now go through Message::toBroadcastArray().
    Event::fake([MessageSent::class]);

    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);

    Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->set('photo', UploadedFile::fake()->image('photo.jpg'))
        ->call('send')
        ->assertHasNoErrors();

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) {
        $payload = $event->broadcastWith();

        expect($payload['media'])->toHaveCount(1);
        expect($payload['media'][0])->toHaveKeys(['url', 'thumbnail_url']);

        return true;
    });
});

test('a media message received live renders on the recipient\'s side without error', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);
    $incoming = $conversation->messages()->create(['user_id' => $b->id, 'body' => '']);
    $incoming->media()->create([
        'user_id' => $b->id, 'disk' => 'public', 'type' => 'image',
        'path' => 'message-media/photo.webp', 'thumbnail_path' => 'message-media/thumb.webp',
        'mime_type' => 'image/webp', 'size' => 100,
    ]);

    Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->call('onMessageReceived', $incoming->toBroadcastArray())
        ->assertSeeHtml('thumb.webp');
});

test('a staged message photo can be removed before sending', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);

    Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->set('body', 'Never mind the photo')
        ->set('photo', UploadedFile::fake()->image('photo.jpg'))
        ->call('removePhoto')
        ->assertSet('photo', null)
        ->call('send')
        ->assertHasNoErrors();

    $message = $conversation->messages()->first();

    expect($message->media)->toHaveCount(0);
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

test('opening a thread marks the other participants messages as read and broadcasts it', function () {
    Event::fake([MessagesRead::class]);

    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);
    $message = $conversation->messages()->create(['user_id' => $b->id, 'body' => 'hi']);

    Livewire::actingAs($a)->test('pages::messages.show', ['conversation' => $conversation]);

    expect($message->fresh()->read_at)->not->toBeNull();

    Event::assertDispatched(MessagesRead::class, function (MessagesRead $event) use ($conversation, $a) {
        return $event->conversation->id === $conversation->id && $event->reader->id === $a->id;
    });
});

test('opening an already-read thread does not re-broadcast', function () {
    Event::fake([MessagesRead::class]);

    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);
    $conversation->messages()->create(['user_id' => $b->id, 'body' => 'hi', 'read_at' => now()]);

    Livewire::actingAs($a)->test('pages::messages.show', ['conversation' => $conversation]);

    Event::assertNotDispatched(MessagesRead::class);
});

test('a newly arrived message while the thread is open is marked read immediately', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);
    $incoming = $conversation->messages()->create(['user_id' => $b->id, 'body' => 'hi']);

    Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->call('onMessageReceived', [
            'id' => $incoming->id,
            'body' => $incoming->body,
            'user_id' => $b->id,
            'user_name' => $b->name,
            'avatar_url' => null,
            'created_at' => $incoming->created_at->toIso8601String(),
            'read_at' => null,
            'media' => [],
        ]);

    expect($incoming->fresh()->read_at)->not->toBeNull();
});

test('receiving a read receipt flips my own sent messages to read in the open thread', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);
    $conversation->messages()->create(['user_id' => $a->id, 'body' => 'seen me?']);

    $component = Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation]);

    expect(collect($component->get('messages'))->first()['read_at'])->toBeNull();

    $component->call('onMessagesRead');

    expect(collect($component->get('messages'))->first()['read_at'])->not->toBeNull();
});

test('typing broadcasts to the other participant but not when messaging is blocked', function () {
    Event::fake([UserTyping::class]);

    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);

    Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->call('notifyTyping');

    Event::assertDispatched(UserTyping::class, fn (UserTyping $event) => $event->user->id === $a->id);

    Event::fake([UserTyping::class]);
    $a->block($b);

    Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->call('notifyTyping');

    Event::assertNotDispatched(UserTyping::class);
});

test('the live listeners use the exact dot prefixed event names Echo requires for read receipts and typing', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);

    $listeners = Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->instance()
        ->getListeners();

    expect(array_keys($listeners))
        ->toContain("echo-private:conversation.{$conversation->id},.MessagesRead")
        ->toContain("echo-private:conversation.{$conversation->id},.UserTyping");
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

// --- hide for me / delete for everyone -------------------------------------

test('hiding a message removes it from just my own view, not the other participant\'s', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);
    $message = $conversation->messages()->create(['user_id' => $b->id, 'body' => 'Hide me']);

    Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->assertSee('Hide me')
        ->call('hideMessage', $message->id)
        ->assertDontSee('Hide me');

    expect($message->isHiddenFor($a))->toBeTrue();
    expect($message->isHiddenFor($b))->toBeFalse();

    Livewire::actingAs($b)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->assertSee('Hide me');
});

test('a hidden message stays hidden across a fresh page load', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);
    $message = $conversation->messages()->create(['user_id' => $b->id, 'body' => 'Hide me']);
    $message->hideFor($a);

    Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->assertDontSee('Hide me');
});

test('a message cannot be hidden twice for the same user', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);
    $message = $conversation->messages()->create(['user_id' => $b->id, 'body' => 'Hide me']);

    $message->hideFor($a);
    $message->hideFor($a);

    expect($message->hides()->where('user_id', $a->id)->count())->toBe(1);
});

test('the sender can delete their own message for everyone, and it broadcasts the redaction', function () {
    Event::fake([MessageDeletedForEveryone::class]);

    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);
    $message = $conversation->messages()->create(['user_id' => $a->id, 'body' => 'Oops, wrong chat']);

    Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->assertSee('Oops, wrong chat')
        ->call('deleteForEveryone', $message->id)
        ->assertDontSee('Oops, wrong chat')
        ->assertSee('This message was deleted.');

    expect($message->fresh()->isDeletedForEveryone())->toBeTrue();

    Event::assertDispatched(MessageDeletedForEveryone::class, function (MessageDeletedForEveryone $event) use ($message) {
        return $event->message->is($message);
    });
});

test('a non-sender cannot delete someone else\'s message for everyone', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);
    $message = $conversation->messages()->create(['user_id' => $b->id, 'body' => 'Not yours to delete']);

    Livewire::actingAs($a)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->call('deleteForEveryone', $message->id)
        ->assertForbidden();

    expect($message->fresh()->isDeletedForEveryone())->toBeFalse();
});

test('deleting for everyone updates the recipient\'s thread live without leaking the original content', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);
    $message = $conversation->messages()->create(['user_id' => $a->id, 'body' => 'Secret content']);

    $sender = Livewire::actingAs($a)->test('pages::messages.show', ['conversation' => $conversation]);
    $sender->call('deleteForEveryone', $message->id);

    $payload = $message->fresh()->toBroadcastArray();

    Livewire::actingAs($b)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->call('onMessageDeletedForEveryone', $payload)
        ->assertDontSee('Secret content')
        ->assertSee('This message was deleted.');
});

test('a message deleted for everyone cannot be un-redacted by a fresh page load', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::between($a, $b);
    $message = $conversation->messages()->create(['user_id' => $a->id, 'body' => 'Secret content']);
    $message->deleteForEveryone($a);

    Livewire::actingAs($b)
        ->test('pages::messages.show', ['conversation' => $conversation])
        ->assertDontSee('Secret content')
        ->assertSee('This message was deleted.');
});
