<?php

use App\Events\MessageDeletedForEveryone;
use App\Events\MessageSent;
use App\Events\MessagesRead;
use App\Events\UserTyping;
use App\Models\Conversation;
use App\Models\LiveSession;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use App\Services\ImageOptimizer;
use App\Support\SafeNotifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Messages')] class extends Component {
    use WithFileUploads;

    public Conversation $conversation;
    public ?User $otherParticipant = null;
    public array $messages = [];
    public string $body = '';
    public $photo = null;
    public bool $canMessage = true;

    public function mount(Conversation $conversation): void
    {
        abort_unless($conversation->participants->contains(Auth::id()), 403);

        $conversation->participants()->updateExistingPivot(Auth::id(), ['last_read_at' => now()]);

        $this->conversation = $conversation;
        $this->otherParticipant = $conversation->participants->firstWhere('id', '!=', Auth::id());
        $this->canMessage = ! $this->otherParticipant || ! Auth::user()->hasBlockRelationWith($this->otherParticipant);

        $this->markIncomingAsRead();

        $this->messages = $conversation->messages()
            ->with(['user.profile', 'media'])
            ->whereDoesntHave('hides', fn ($query) => $query->where('user_id', Auth::id()))
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $message) => $message->toBroadcastArray())
            ->all();
    }

    /**
     * Marks every message from the other participant as read and lets
     * their own open thread know, if any of them actually were unread —
     * avoids a pointless broadcast (and a "Seen" flicker) on every mount.
     */
    protected function markIncomingAsRead(): void
    {
        $marked = $this->conversation->messages()
            ->where('user_id', '!=', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($marked > 0) {
            try {
                broadcast(new MessagesRead($this->conversation, Auth::user()))->toOthers();
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public function removePhoto(): void
    {
        $this->reset('photo');
    }

    /**
     * $canMessage is a plain public property set once in mount() — Livewire
     * lets a client-supplied property update override it ahead of a method
     * call, so every action that depends on the block check re-derives it
     * here instead of trusting the cached value.
     */
    protected function canActuallyMessage(): bool
    {
        return ! $this->otherParticipant || ! Auth::user()->hasBlockRelationWith($this->otherParticipant);
    }

    /**
     * Caps how many messages one user can send across all their
     * conversations per minute — guards against flooding a participant's
     * inbox in a tight loop, not normal back-and-forth chatting.
     */
    private const MAX_MESSAGES_PER_MINUTE = 60;

    public function send(): void
    {
        abort_unless($this->canActuallyMessage(), 403);

        $throttleKey = 'send-message:'.Auth::id();
        abort_if(RateLimiter::tooManyAttempts($throttleKey, self::MAX_MESSAGES_PER_MINUTE), 429);
        RateLimiter::hit($throttleKey, 60);

        $this->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'max:20480', Rule::dimensions()->maxWidth(6000)->maxHeight(6000)],
        ]);

        if (blank($this->body) && ! $this->photo) {
            $this->addError('body', __('Write something or add a photo.'));

            return;
        }

        $message = $this->conversation->messages()->create([
            'user_id' => Auth::id(),
            'body' => $this->body !== '' ? $this->body : null,
        ]);

        if ($this->photo) {
            $optimized = ImageOptimizer::store($this->photo, 'message-media', 'public');

            $message->media()->create([
                'user_id' => Auth::id(),
                'disk' => 'public',
                'type' => 'image',
                ...$optimized,
            ]);
        }

        $this->messages[] = $message->toBroadcastArray();

        $this->conversation->participants()->updateExistingPivot(Auth::id(), ['last_read_at' => now()]);

        if ($this->otherParticipant) {
            SafeNotifier::send($this->otherParticipant, new NewMessageReceived($message));
        }

        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }

        $this->reset(['body', 'photo']);
    }

    public function startCall()
    {
        abort_unless($this->otherParticipant, 404);
        abort_unless($this->canActuallyMessage(), 403);

        $session = LiveSession::startCallWith(Auth::user(), $this->otherParticipant);

        return $this->redirect(route('live.show', $session), navigate: true);
    }

    /**
     * "Hide for me" — local-only, nothing broadcast. findOrFail (scoped to
     * this conversation) is the same backstop used everywhere else a
     * client-supplied id reaches a write: it 404s instead of trusting that
     * the id actually belongs to this thread.
     */
    public function hideMessage(int $id): void
    {
        $message = $this->conversation->messages()->findOrFail($id);
        $message->hideFor(Auth::user());

        $this->messages = collect($this->messages)->reject(fn ($m) => $m['id'] === $id)->values()->all();
    }

    public function deleteForEveryone(int $id): void
    {
        $message = $this->conversation->messages()->findOrFail($id);
        $message->deleteForEveryone(Auth::user());

        $redacted = $message->toBroadcastArray();

        $this->messages = collect($this->messages)
            ->map(fn ($m) => $m['id'] === $id ? $redacted : $m)
            ->all();

        try {
            broadcast(new MessageDeletedForEveryone($message))->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function getListeners(): array
    {
        return [
            "echo-private:conversation.{$this->conversation->id},.MessageSent" => 'onMessageReceived',
            "echo-private:conversation.{$this->conversation->id},.MessagesRead" => 'onMessagesRead',
            "echo-private:conversation.{$this->conversation->id},.UserTyping" => 'onTypingReceived',
            "echo-private:conversation.{$this->conversation->id},.MessageDeletedForEveryone" => 'onMessageDeletedForEveryone',
        ];
    }

    public function onMessageDeletedForEveryone(array $event): void
    {
        $this->messages = collect($this->messages)
            ->map(fn ($m) => $m['id'] === $event['id'] ? $event : $m)
            ->all();
    }

    public function onMessageReceived(array $event): void
    {
        $this->messages[] = $event;

        $this->conversation->participants()->updateExistingPivot(Auth::id(), ['last_read_at' => now()]);

        // The thread is open right now, so this just-arrived message is
        // seen immediately — mark it read and let the sender's own open
        // thread know, same as markIncomingAsRead() does on mount.
        $this->markIncomingAsRead();
    }

    /**
     * The other participant just caught up — flip every message I sent to
     * read so the checkmarks update without a reload.
     */
    public function onMessagesRead(): void
    {
        $this->messages = collect($this->messages)
            ->map(function (array $message) {
                if ($message['user_id'] === Auth::id() && ! $message['read_at']) {
                    $message['read_at'] = now()->toIso8601String();
                }

                return $message;
            })
            ->all();
    }

    public function onTypingReceived(): void
    {
        $this->dispatch('other-typing');
    }

    /**
     * The reactions widget needs a real Message instance per row, but
     * $messages is a plain array (for broadcast/serialization). Batches
     * every visible message into one query instead of fetching each row's
     * model individually in the render loop, and eager-loads the counts
     * HasReactions falls back to a live query for otherwise.
     */
    #[Computed]
    public function messageModels(): \Illuminate\Support\Collection
    {
        return Message::query()
            ->whereIn('id', collect($this->messages)->pluck('id'))
            ->withCount('reactions')
            ->withExists(['reactions as user_reacted' => fn ($query) => $query->where('user_id', Auth::id())])
            ->get()
            ->keyBy('id');
    }

    public function notifyTyping(): void
    {
        if (! $this->otherParticipant || ! $this->canMessage) {
            return;
        }

        try {
            broadcast(new UserTyping($this->conversation, Auth::user()))->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}; ?>

<div class="mx-auto flex h-full min-h-0 w-full max-w-2xl flex-col">
    {{-- Height comes entirely from whatever wraps this component — the
         messages panel's own explicit height, on both the /messages list
         page and a direct /messages/{conversation} link, now that they're
         the same component (see pages::messages.inbox). min-h-0 is the
         actual fix: without it, a flex child ignores its parent's height
         and grows to fit its own content instead, which is what was
         silently pushing the composer below the visible/clipped area. --}}
    <div class="flex shrink-0 items-center gap-3 border-b border-stone-200 pb-4 dark:border-stone-800">
        {{-- Only meaningful on mobile, where this component fills the whole
             screen — on desktop it's embedded next to the conversation
             list it would navigate back to, so the button is redundant. --}}
        <flux:button :href="route('messages.index')" wire:navigate size="sm" variant="ghost" icon="arrow-left" class="lg:hidden" />

        @if ($otherParticipant)
            <a href="{{ route('profile.show', $otherParticipant) }}" wire:navigate class="size-9 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                @if ($otherParticipant->profile?->avatarUrl())
                    <img src="{{ $otherParticipant->profile->avatarUrl() }}" class="size-full object-cover">
                @else
                    <div class="flex size-full items-center justify-center text-stone-500">
                        <flux:icon.user class="size-4" />
                    </div>
                @endif
            </a>

            <flux:link :href="route('profile.show', $otherParticipant)" wire:navigate class="min-w-0 flex-1 truncate font-medium text-stone-900 dark:text-white">
                {{ $otherParticipant->name }}
            </flux:link>
        @else
            <div class="size-9 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                <div class="flex size-full items-center justify-center text-stone-500">
                    <flux:icon.user class="size-4" />
                </div>
            </div>

            <span class="min-w-0 flex-1 truncate font-medium text-stone-900 dark:text-white">{{ __('Unknown') }}</span>
        @endif

        @if ($canMessage && config('features.live'))
            <flux:button wire:click="startCall" wire:loading.attr="disabled" size="sm" variant="ghost" icon="video-camera" class="max-sm:w-8! max-sm:gap-0! max-sm:ps-0! max-sm:pe-0!" data-test="start-call-button">
                <span class="hidden sm:inline">{{ __('Call') }}</span>
            </flux:button>
        @elseif ($canMessage)
            <flux:button disabled size="sm" variant="ghost" icon="video-camera" class="max-sm:w-8! max-sm:gap-0! max-sm:ps-0! max-sm:pe-0!" data-test="call-coming-soon">
                <span class="hidden items-center gap-2 sm:inline-flex">{{ __('Call') }} <x-coming-soon-badge /></span>
            </flux:button>
        @endif
    </div>

    <div
        x-data="{ typing: false, timer: null }"
        x-on:other-typing.window="typing = true; clearTimeout(timer); timer = setTimeout(() => typing = false, 3000)"
        x-init="$watch('$wire.messages', () => $nextTick(() => $el.scrollTop = $el.scrollHeight)); $el.scrollTop = $el.scrollHeight"
        class="scrollbar-thin min-h-0 flex-1 space-y-3 overflow-y-auto py-4"
    >
        @foreach ($messages as $message)
            @php
                $isMine = $message['user_id'] === Auth::id();
                $isDeleted = $message['deleted_for_everyone'] ?? false;
            @endphp

            <div class="flex flex-col {{ $isMine ? 'items-end' : 'items-start' }}" wire:key="message-{{ $message['id'] }}">
                {{-- max-w lives here, not on the bubble below — this row is
                     a flex item of a flex-col with items-end/items-start,
                     so it's already cross-axis shrink-to-fit sized against
                     a definite width. A % max-width on the bubble instead
                     (one level deeper) resolves against THIS row's width
                     before that shrink-to-fit sizing has settled, which
                     reads as indeterminate and makes the text wrap earlier
                     than the real 85% limit — sometimes mid-sentence with
                     room to spare. --}}
                <div class="flex max-w-[85%] items-end gap-1 {{ $isMine ? 'flex-row-reverse' : 'flex-row' }}">
                    <div class="min-w-0 rounded-2xl px-4 py-2 {{ $isMine ? 'bg-messages-600 text-white' : 'bg-stone-100 text-stone-900 dark:bg-stone-800 dark:text-stone-100' }}">
                        @if ($isDeleted)
                            <p class="text-sm italic {{ $isMine ? 'text-messages-100' : 'text-stone-400 dark:text-stone-500' }}">
                                {{ __('This message was deleted.') }}
                            </p>
                        @else
                            @if (! empty($message['media']))
                                @php $mediaUrls = array_column($message['media'], 'url'); @endphp
                                <div class="mb-1 grid gap-1 {{ count($message['media']) > 1 ? 'grid-cols-2' : '' }}" x-data>
                                    @foreach ($message['media'] as $index => $media)
                                        <button type="button" x-on:click="window.dispatchEvent(new CustomEvent('media-viewer:show', { detail: { images: @js($mediaUrls), index: {{ $index }} } }))" class="block">
                                            <img src="{{ $media['thumbnail_url'] }}" class="max-h-64 w-full rounded-lg object-cover">
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @if ($message['body'])
                                <p class="whitespace-pre-line text-sm">{{ $message['body'] }}</p>
                            @endif
                        @endif
                    </div>

                    @unless ($isDeleted)
                        <flux:dropdown position="bottom" align="{{ $isMine ? 'end' : 'start' }}">
                            <button
                                type="button"
                                class="flex size-6 shrink-0 items-center justify-center rounded-md text-stone-300 hover:bg-stone-100 hover:text-stone-600 dark:text-stone-600 dark:hover:bg-stone-800 dark:hover:text-stone-300"
                                aria-label="{{ __('Message options') }}"
                            >
                                <flux:icon.ellipsis-horizontal class="size-4" />
                            </button>

                            <flux:menu>
                                <flux:menu.item wire:click="hideMessage({{ $message['id'] }})" icon="eye-slash">
                                    {{ __('Hide for me') }}
                                </flux:menu.item>

                                @if ($isMine)
                                    <flux:menu.item
                                        wire:click="deleteForEveryone({{ $message['id'] }})"
                                        wire:confirm="{{ __('Delete this message for everyone? This cannot be undone.') }}"
                                        variant="danger"
                                        icon="trash"
                                    >
                                        {{ __('Delete for everyone') }}
                                    </flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    @endunless
                </div>

                @if ($isMine && ! $isDeleted)
                    <span class="mt-0.5 flex items-center gap-0.5 text-xs {{ $message['read_at'] ? 'text-messages-600 dark:text-messages-400' : 'text-stone-400 dark:text-stone-500' }}" data-test="read-receipt">
                        @if ($message['read_at'])
                            <flux:icon.check-circle variant="solid" class="size-3.5" />
                            {{ __('Seen') }}
                        @else
                            <flux:icon.check class="size-3.5" />
                        @endif
                    </span>
                @endif

                @if (! $isDeleted && $messageModel = $this->messageModels->get($message['id']))
                    <livewire:pages::shared.reactions :reactable="$messageModel" :key="'message-reactions-'.$message['id']" />
                @endif
            </div>
        @endforeach

        <div x-show="typing" style="display: none;" class="flex items-center gap-1.5 px-1 text-xs text-stone-400 dark:text-stone-500" data-test="typing-indicator">
            <flux:icon.loading variant="micro" class="size-3.5" />
            {{ __(':name is typing…', ['name' => $otherParticipant?->name ?? __('They')]) }}
        </div>
    </div>

    @if (! $canMessage)
        <div class="border-t border-stone-200 pt-4 text-center text-sm text-stone-500 dark:border-stone-800 dark:text-stone-400">
            {{ __('You can no longer message each other.') }}
        </div>
    @else
    <form
        wire:submit="send"
        class="shrink-0 border-t border-stone-200 pt-4 dark:border-stone-800"
        x-data="{ uploading: false, progress: 0 }"
        x-on:livewire-upload-start="uploading = true; progress = 0"
        x-on:livewire-upload-finish="uploading = false"
        x-on:livewire-upload-cancel="uploading = false"
        x-on:livewire-upload-error="uploading = false"
        x-on:livewire-upload-progress="progress = $event.detail.progress"
    >
        @if ($photo)
            <div class="mb-2 flex items-center gap-2">
                <div class="relative size-16 shrink-0 overflow-hidden rounded-lg bg-stone-100 dark:bg-stone-800">
                    <img src="{{ $photo->temporaryUrl() }}" class="size-full object-cover">
                    <button
                        type="button"
                        wire:click="removePhoto"
                        class="absolute top-0.5 end-0.5 flex size-4 items-center justify-center rounded-full bg-black/60 text-white hover:bg-black/80"
                    >
                        <flux:icon.x-mark class="size-2.5" />
                    </button>
                </div>
                <span x-show="uploading" style="display: none;" class="flex items-center gap-1.5 text-sm text-messages-600 dark:text-messages-400">
                    <flux:icon.loading variant="micro" class="size-3.5" />
                    <span x-text="{{ Js::from(__('Uploading…')) }} + ' ' + progress + '%'"></span>
                </span>
            </div>
        @endif

        <div class="flex items-end gap-2">
            <label class="cursor-pointer rounded-md p-2 text-stone-500 hover:bg-stone-100 hover:text-messages-600 dark:text-stone-400 dark:hover:bg-stone-800 dark:hover:text-messages-400">
                <input type="file" wire:model="photo" accept="image/*" class="hidden">
                <flux:icon.photo class="size-5" />
            </label>

            <flux:textarea
                wire:model="body"
                wire:keydown.debounce.500ms="notifyTyping"
                placeholder="{{ __('Write a message...') }}"
                rows="1"
                class="messages-input flex-1"
            />

            <flux:button
                type="submit"
                variant="primary"
                icon="paper-airplane"
                wire:loading.attr="disabled"
                wire:target="send"
                aria-label="{{ __('Send') }}"
                class="!bg-messages-600 hover:!bg-messages-500 dark:!bg-messages-600 dark:hover:!bg-messages-500 w-8! gap-0! ps-0! pe-0!"
            />
        </div>

        @error('body') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('photo') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </form>
    @endif
</div>
