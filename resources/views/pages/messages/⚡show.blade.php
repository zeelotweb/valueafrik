<?php

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
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $message) => $this->formatMessage($message))
            ->all();
    }

    protected function formatMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'user_id' => $message->user_id,
            'user_name' => $message->user->name,
            'avatar_url' => $message->user->profile?->avatarUrl(),
            'created_at' => $message->created_at->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'media' => $message->media->map(fn ($media) => ['url' => $media->url()])->all(),
        ];
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

    public function send(): void
    {
        abort_unless($this->canMessage, 403);

        $this->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'max:8192'],
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

        $message->load(['user.profile', 'media']);

        $this->messages[] = $this->formatMessage($message);

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
        abort_unless($this->canMessage, 403);

        $session = LiveSession::startCallWith(Auth::user(), $this->otherParticipant);

        return $this->redirect(route('live.show', $session), navigate: true);
    }

    public function getListeners(): array
    {
        return [
            "echo-private:conversation.{$this->conversation->id},.MessageSent" => 'onMessageReceived',
            "echo-private:conversation.{$this->conversation->id},.MessagesRead" => 'onMessagesRead',
            "echo-private:conversation.{$this->conversation->id},.UserTyping" => 'onTypingReceived',
        ];
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

<div class="mx-auto flex h-[calc(100vh-8rem)] w-full max-w-2xl flex-col">
    <div class="flex items-center gap-3 border-b border-stone-200 pb-4 dark:border-stone-800">
        <flux:button :href="route('messages.index')" wire:navigate size="sm" variant="ghost" icon="arrow-left" />

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

        @if ($canMessage)
            <flux:button wire:click="startCall" wire:loading.attr="disabled" size="sm" variant="ghost" icon="video-camera" class="max-sm:w-8! max-sm:gap-0! max-sm:ps-0! max-sm:pe-0!" data-test="start-call-button">
                <span class="hidden sm:inline">{{ __('Call') }}</span>
            </flux:button>
        @endif
    </div>

    <div
        x-data="{ typing: false, timer: null }"
        x-on:other-typing.window="typing = true; clearTimeout(timer); timer = setTimeout(() => typing = false, 3000)"
        x-init="$watch('$wire.messages', () => $nextTick(() => $el.scrollTop = $el.scrollHeight)); $el.scrollTop = $el.scrollHeight"
        class="flex-1 space-y-3 overflow-y-auto py-4"
    >
        @foreach ($messages as $message)
            @php $isMine = $message['user_id'] === Auth::id(); @endphp

            <div class="flex flex-col {{ $isMine ? 'items-end' : 'items-start' }}" wire:key="message-{{ $message['id'] }}">
                <div class="max-w-[75%] rounded-2xl px-4 py-2 {{ $isMine ? 'bg-cyan-600 text-white' : 'bg-stone-100 text-stone-900 dark:bg-stone-800 dark:text-stone-100' }}">
                    @if (! empty($message['media']))
                        @php $mediaUrls = array_column($message['media'], 'url'); @endphp
                        <div class="mb-1 grid gap-1 {{ count($message['media']) > 1 ? 'grid-cols-2' : '' }}" x-data>
                            @foreach ($message['media'] as $index => $media)
                                <button type="button" x-on:click="window.dispatchEvent(new CustomEvent('media-viewer:show', { detail: { images: @js($mediaUrls), index: {{ $index }} } }))" class="block">
                                    <img src="{{ $media['url'] }}" class="max-h-64 w-full rounded-lg object-cover">
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @if ($message['body'])
                        <p class="whitespace-pre-line text-sm">{{ $message['body'] }}</p>
                    @endif
                </div>

                @if ($isMine)
                    <span class="mt-0.5 flex items-center gap-0.5 text-xs {{ $message['read_at'] ? 'text-cyan-600 dark:text-cyan-400' : 'text-stone-400 dark:text-stone-500' }}" data-test="read-receipt">
                        @if ($message['read_at'])
                            <flux:icon.check-circle variant="solid" class="size-3.5" />
                            {{ __('Seen') }}
                        @else
                            <flux:icon.check class="size-3.5" />
                        @endif
                    </span>
                @endif

                @if ($messageModel = \App\Models\Message::find($message['id']))
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
        class="border-t border-stone-200 pt-4 dark:border-stone-800"
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
                <span x-show="uploading" style="display: none;" class="flex items-center gap-1.5 text-sm text-cyan-600 dark:text-cyan-400">
                    <flux:icon.loading variant="micro" class="size-3.5" />
                    <span x-text="{{ Js::from(__('Uploading…')) }} + ' ' + progress + '%'"></span>
                </span>
            </div>
        @endif

        <div class="flex items-end gap-2">
            <label class="cursor-pointer rounded-md p-2 text-stone-500 hover:bg-stone-100 hover:text-cyan-600 dark:text-stone-400 dark:hover:bg-stone-800 dark:hover:text-cyan-400">
                <input type="file" wire:model="photo" accept="image/*" class="hidden">
                <flux:icon.photo class="size-5" />
            </label>

            <flux:textarea
                wire:model="body"
                wire:keydown.debounce.500ms="notifyTyping"
                placeholder="{{ __('Write a message...') }}"
                rows="1"
                class="flex-1"
            />

            <flux:button type="submit" variant="primary" color="cyan" wire:loading.attr="disabled" wire:target="send">
                {{ __('Send') }}
            </flux:button>
        </div>

        @error('body') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('photo') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </form>
    @endif
</div>
