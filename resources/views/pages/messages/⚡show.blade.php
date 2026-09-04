<?php

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\LiveSession;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageReceived;
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

    public function mount(Conversation $conversation): void
    {
        abort_unless($conversation->participants->contains(Auth::id()), 403);

        $conversation->participants()->updateExistingPivot(Auth::id(), ['last_read_at' => now()]);

        $this->conversation = $conversation;
        $this->otherParticipant = $conversation->participants->firstWhere('id', '!=', Auth::id());

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
            'media' => $message->media->map(fn ($media) => ['url' => $media->url()])->all(),
        ];
    }

    public function removePhoto(): void
    {
        $this->reset('photo');
    }

    public function send(): void
    {
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
            $message->media()->create([
                'user_id' => Auth::id(),
                'disk' => 'public',
                'path' => $this->photo->store('message-media', 'public'),
                'mime_type' => $this->photo->getMimeType(),
                'type' => 'image',
                'size' => $this->photo->getSize(),
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

        $session = LiveSession::startCallWith(Auth::user(), $this->otherParticipant);

        return $this->redirect(route('live.show', $session), navigate: true);
    }

    public function getListeners(): array
    {
        return [
            "echo-private:conversation.{$this->conversation->id},.MessageSent" => 'onMessageReceived',
        ];
    }

    public function onMessageReceived(array $event): void
    {
        $this->messages[] = $event;

        $this->conversation->participants()->updateExistingPivot(Auth::id(), ['last_read_at' => now()]);
    }
}; ?>

<div class="mx-auto flex h-[calc(100vh-8rem)] w-full max-w-2xl flex-col">
    <div class="flex items-center gap-3 border-b border-stone-200 pb-4 dark:border-stone-800">
        <flux:button :href="route('messages.index')" wire:navigate size="sm" variant="ghost" icon="arrow-left" />

        <div class="size-9 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
            @if ($otherParticipant?->profile?->avatarUrl())
                <img src="{{ $otherParticipant->profile->avatarUrl() }}" class="size-full object-cover">
            @else
                <div class="flex size-full items-center justify-center text-stone-500">
                    <flux:icon.user class="size-4" />
                </div>
            @endif
        </div>

        <flux:link :href="route('profile.show', $otherParticipant)" wire:navigate class="min-w-0 flex-1 truncate font-medium text-stone-900 dark:text-white">
            {{ $otherParticipant?->name ?? __('Unknown') }}
        </flux:link>

        <flux:button wire:click="startCall" wire:loading.attr="disabled" size="sm" variant="ghost" icon="video-camera" data-test="start-call-button">
            <span class="hidden sm:inline">{{ __('Call') }}</span>
        </flux:button>
    </div>

    <div
        x-data
        x-init="$watch('$wire.messages', () => $nextTick(() => $el.scrollTop = $el.scrollHeight)); $el.scrollTop = $el.scrollHeight"
        class="flex-1 space-y-3 overflow-y-auto py-4"
    >
        @foreach ($messages as $message)
            @php $isMine = $message['user_id'] === Auth::id(); @endphp

            <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}" wire:key="message-{{ $message['id'] }}">
                <div class="max-w-[75%] rounded-2xl px-4 py-2 {{ $isMine ? 'bg-green-600 text-white' : 'bg-stone-100 text-stone-900 dark:bg-stone-800 dark:text-stone-100' }}">
                    @if (! empty($message['media']))
                        <div class="mb-1 grid gap-1 {{ count($message['media']) > 1 ? 'grid-cols-2' : '' }}">
                            @foreach ($message['media'] as $media)
                                <img src="{{ $media['url'] }}" class="max-h-64 w-full rounded-lg object-cover">
                            @endforeach
                        </div>
                    @endif

                    @if ($message['body'])
                        <p class="whitespace-pre-line text-sm">{{ $message['body'] }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

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
                <span x-show="uploading" style="display: none;" class="flex items-center gap-1.5 text-sm text-green-600 dark:text-green-400">
                    <flux:icon.loading variant="micro" class="size-3.5" />
                    <span x-text="{{ Js::from(__('Uploading…')) }} + ' ' + progress + '%'"></span>
                </span>
            </div>
        @endif

        <div class="flex items-end gap-2">
            <label class="cursor-pointer rounded-md p-2 text-stone-500 hover:bg-stone-100 hover:text-green-600 dark:text-stone-400 dark:hover:bg-stone-800 dark:hover:text-green-400">
                <input type="file" wire:model="photo" accept="image/*" class="hidden">
                <flux:icon.photo class="size-5" />
            </label>

            <flux:textarea
                wire:model="body"
                placeholder="{{ __('Write a message...') }}"
                rows="1"
                class="flex-1"
            />

            <flux:button type="submit" variant="primary" color="green" wire:loading.attr="disabled" wire:target="send">
                {{ __('Send') }}
            </flux:button>
        </div>

        @error('body') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('photo') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </form>
</div>
