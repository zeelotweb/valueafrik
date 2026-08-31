<?php

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
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

        broadcast(new MessageSent($message))->toOthers();

        $this->reset(['body', 'photo']);
    }

    public function getListeners(): array
    {
        return [
            "echo-private:conversation.{$this->conversation->id},MessageSent" => 'onMessageReceived',
        ];
    }

    public function onMessageReceived(array $event): void
    {
        $this->messages[] = $event;

        $this->conversation->participants()->updateExistingPivot(Auth::id(), ['last_read_at' => now()]);
    }
}; ?>

<div class="mx-auto flex h-[calc(100vh-8rem)] w-full max-w-2xl flex-col">
    <div class="flex items-center gap-3 border-b border-zinc-200 pb-4 dark:border-zinc-700">
        <flux:button :href="route('messages.index')" wire:navigate size="sm" variant="ghost" icon="arrow-left" />

        <div class="size-9 shrink-0 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
            @if ($otherParticipant?->profile?->avatarUrl())
                <img src="{{ $otherParticipant->profile->avatarUrl() }}" class="size-full object-cover">
            @else
                <div class="flex size-full items-center justify-center text-zinc-500">
                    <flux:icon.user class="size-4" />
                </div>
            @endif
        </div>

        <flux:link :href="route('profile.show', $otherParticipant)" wire:navigate class="font-medium text-zinc-900 dark:text-white">
            {{ $otherParticipant?->name ?? __('Unknown') }}
        </flux:link>
    </div>

    <div
        x-data
        x-init="$watch('$wire.messages', () => $nextTick(() => $el.scrollTop = $el.scrollHeight)); $el.scrollTop = $el.scrollHeight"
        class="flex-1 space-y-3 overflow-y-auto py-4"
    >
        @foreach ($messages as $message)
            @php $isMine = $message['user_id'] === Auth::id(); @endphp

            <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}" wire:key="message-{{ $message['id'] }}">
                <div class="max-w-[75%] rounded-2xl px-4 py-2 {{ $isMine ? 'bg-cyan-600 text-white' : 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' }}">
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

    <form wire:submit="send" class="border-t border-zinc-200 pt-4 dark:border-zinc-700">
        @if ($photo)
            <div class="mb-2">
                <img src="{{ $photo->temporaryUrl() }}" class="size-20 rounded-lg object-cover">
            </div>
        @endif

        <div class="flex items-end gap-2">
            <label class="cursor-pointer p-2 text-zinc-500 hover:text-cyan-600 dark:text-zinc-400">
                <input type="file" wire:model="photo" accept="image/*" class="hidden">
                <flux:icon.photo class="size-5" />
            </label>

            <flux:textarea
                wire:model="body"
                placeholder="{{ __('Write a message...') }}"
                rows="1"
                class="flex-1"
            />

            <flux:button type="submit" variant="primary" class="!bg-cyan-600 hover:!bg-cyan-500" wire:loading.attr="disabled">
                {{ __('Send') }}
            </flux:button>
        </div>

        @error('body') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('photo') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </form>
</div>
