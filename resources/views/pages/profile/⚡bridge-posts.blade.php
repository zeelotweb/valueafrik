<?php

use App\Models\BridgePost;
use App\Models\User;
use App\Notifications\BridgePostCompleted;
use App\Support\SafeNotifier;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public User $user;
    public ?int $editingId = null;
    public string $sideBody = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile[] */
    public array $sidePhotos = [];

    public function startSide(int $bridgePostId): void
    {
        $this->editingId = $bridgePostId;
        $this->sideBody = '';
        $this->sidePhotos = [];
    }

    public function removeSidePhoto(int $index): void
    {
        unset($this->sidePhotos[$index]);

        $this->sidePhotos = array_values($this->sidePhotos);
    }

    public function cancelSide(): void
    {
        $this->editingId = null;
        $this->reset(['sideBody', 'sidePhotos']);
    }

    public function submitSide(): void
    {
        $bridgePost = BridgePost::findOrFail($this->editingId);
        $side = $bridgePost->sideFor(Auth::user());

        abort_if($side === null, 403);

        $this->validate([
            'sideBody' => ['required', 'string', 'max:3000'],
            'sidePhotos' => ['array', 'max:4'],
            'sidePhotos.*' => ['image', 'max:8192'],
        ]);

        $bridgePost->update(["{$side}_body" => $this->sideBody]);

        foreach ($this->sidePhotos as $photo) {
            $bridgePost->media()->create([
                'user_id' => Auth::id(),
                'disk' => 'public',
                'path' => $photo->store('bridge-post-media', 'public'),
                'mime_type' => $photo->getMimeType(),
                'type' => 'image',
                'size' => $photo->getSize(),
            ]);
        }

        if ($bridgePost->fresh()->isComplete()) {
            $bridgePost->initiator->awardBridgeScore('bridge_post_completed', $bridgePost);
            $bridgePost->partner->awardBridgeScore('bridge_post_completed', $bridgePost);

            $other = $side === 'initiator' ? $bridgePost->partner : $bridgePost->initiator;
            SafeNotifier::send($other, new BridgePostCompleted($bridgePost, Auth::user()));
        }

        $this->editingId = null;
        $this->reset(['sideBody', 'sidePhotos']);
    }

    public function with(): array
    {
        $bridgePosts = BridgePost::query()
            ->where('status', BridgePost::STATUS_ACTIVE)
            ->where(fn ($query) => $query->where('initiator_id', $this->user->id)->orWhere('partner_id', $this->user->id))
            ->with(['initiator.profile', 'partner.profile', 'media'])
            ->latest()
            ->get();

        return ['bridgePosts' => $bridgePosts];
    }
}; ?>

<div class="mb-6 space-y-4" wire:key="bridge-posts-{{ $user->id }}">
    @foreach ($bridgePosts as $post)
        @php
            $viewerSide = $post->sideFor(Auth::user());
            $initiatorMedia = $post->media->where('user_id', $post->initiator_id);
            $partnerMedia = $post->media->where('user_id', $post->partner_id);
        @endphp

        <div class="overflow-hidden rounded-xl border border-cyan-200 dark:border-cyan-900" wire:key="bridge-post-{{ $post->id }}">
            <div class="flex items-center gap-2 bg-cyan-50 px-4 py-2 dark:bg-cyan-950/40">
                <flux:icon.arrows-right-left class="size-4 text-cyan-600 dark:text-cyan-400" />
                <span class="text-sm font-medium text-cyan-700 dark:text-cyan-300">{{ __('Bridge Post') }} — {{ $post->theme }}</span>
            </div>

            <div class="grid divide-y divide-stone-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0 dark:divide-stone-800">
                @foreach ([
                    ['user' => $post->initiator, 'body' => $post->initiator_body, 'media' => $initiatorMedia, 'side' => 'initiator'],
                    ['user' => $post->partner, 'body' => $post->partner_body, 'media' => $partnerMedia, 'side' => 'partner'],
                ] as $column)
                    <div class="p-4">
                        <div class="flex items-center gap-2">
                            <div class="size-8 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                                @if ($column['user']->profile?->avatarUrl())
                                    <img src="{{ $column['user']->profile->avatarUrl() }}" class="size-full object-cover">
                                @else
                                    <div class="flex size-full items-center justify-center text-stone-500">
                                        <flux:icon.user class="size-4" />
                                    </div>
                                @endif
                            </div>
                            <span class="text-sm font-medium text-stone-900 dark:text-white">{{ $column['user']->name }}</span>
                        </div>

                        @if ($column['body'])
                            <p class="mt-3 whitespace-pre-line text-sm text-stone-700 dark:text-stone-300">{{ $column['body'] }}</p>

                            @if ($column['media']->isNotEmpty())
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    @foreach ($column['media'] as $media)
                                        <img src="{{ $media->url() }}" class="aspect-square w-full rounded-lg object-cover">
                                    @endforeach
                                </div>
                            @endif
                        @elseif ($viewerSide === $column['side'])
                            @if ($editingId === $post->id)
                                <div class="mt-3 space-y-2">
                                    <flux:textarea wire:model="sideBody" rows="3" placeholder="{{ __('Your side of the story…') }}" />

                                    @include('partials.photo-picker', ['photos' => $sidePhotos, 'property' => 'sidePhotos', 'removeMethod' => 'removeSidePhoto', 'max' => 4])

                                    <div class="flex items-center justify-end gap-2">
                                        <flux:button size="sm" variant="ghost" wire:click="cancelSide">{{ __('Cancel') }}</flux:button>
                                        <flux:button size="sm" variant="primary" color="cyan" wire:click="submitSide" wire:loading.attr="disabled" wire:target="submitSide">
                                            {{ __('Post my side') }}
                                        </flux:button>
                                    </div>

                                    @error('sideBody') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @else
                                <flux:button size="sm" variant="ghost" class="mt-3" wire:click="startSide({{ $post->id }})">
                                    {{ __('Add your side') }}
                                </flux:button>
                            @endif
                        @else
                            <p class="mt-3 text-sm italic text-stone-400 dark:text-stone-500">
                                {{ __('Waiting for :name to add their side.', ['name' => $column['user']->name]) }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
