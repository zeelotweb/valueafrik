<?php

use App\Models\BridgePost;
use App\Models\User;
use App\Notifications\BridgePostCompleted;
use App\Services\ImageOptimizer;
use App\Support\SafeNotifier;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public User $user;
    public ?int $editingId = null;
    public string $sideBody = '';
    public int $perPage = 20;
    public int $loaded = 20;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile[] */
    public array $sidePhotos = [];

    public function startSide(int $bridgePostId): void
    {
        $this->editingId = $bridgePostId;
        $this->sideBody = '';
        $this->sidePhotos = [];

        $this->modal('bridge-post-side-'.$bridgePostId)->show();
    }

    public function removeSidePhoto(int $index): void
    {
        unset($this->sidePhotos[$index]);

        $this->sidePhotos = array_values($this->sidePhotos);
    }

    public function cancelSide(): void
    {
        if ($this->editingId) {
            $this->modal('bridge-post-side-'.$this->editingId)->close();
        }

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
            'sidePhotos' => ['array', 'max:5'],
            'sidePhotos.*' => ['image', 'max:8192'],
        ]);

        $bridgePost->update(["{$side}_body" => $this->sideBody]);

        foreach ($this->sidePhotos as $photo) {
            $optimized = ImageOptimizer::store($photo, 'bridge-post-media', 'public');

            $bridgePost->media()->create([
                'user_id' => Auth::id(),
                'disk' => 'public',
                'type' => 'image',
                ...$optimized,
            ]);
        }

        // isComplete() just checks both bodies are non-empty, which stays
        // true forever once reached — without this guard, either side
        // re-submitting (e.g. to add another photo) re-triggers the
        // completion award and notification every time.
        if ($bridgePost->fresh()->isComplete()
            && ! $bridgePost->initiator->hasEarnedBridgeScoreFor('bridge_post_completed', $bridgePost)) {
            $bridgePost->initiator->awardBridgeScore('bridge_post_completed', $bridgePost);
            $bridgePost->partner->awardBridgeScore('bridge_post_completed', $bridgePost);

            $other = $side === 'initiator' ? $bridgePost->partner : $bridgePost->initiator;
            SafeNotifier::send($other, new BridgePostCompleted($bridgePost, Auth::user()));
        }

        $this->modal('bridge-post-side-'.$this->editingId)->close();

        $this->editingId = null;
        $this->reset(['sideBody', 'sidePhotos']);
    }

    public function loadMore(): void
    {
        if ($this->hasMore) {
            $this->loaded += $this->perPage;

            unset($this->bridgePostsWindow, $this->hasMore);
        }
    }

    #[Computed]
    public function bridgePostsWindow()
    {
        return BridgePost::query()
            ->where('status', BridgePost::STATUS_ACTIVE)
            ->where(fn ($query) => $query->where('initiator_id', $this->user->id)->orWhere('partner_id', $this->user->id))
            ->with(['initiator.profile', 'partner.profile', 'media'])
            ->latest()
            ->limit($this->loaded + 1)
            ->get();
    }

    #[Computed]
    public function hasMore(): bool
    {
        return $this->bridgePostsWindow->count() > $this->loaded;
    }

    public function with(): array
    {
        return ['bridgePosts' => $this->bridgePostsWindow->take($this->loaded)];
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
                            <a href="{{ route('profile.show', $column['user']) }}" wire:navigate class="size-8 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                                @if ($column['user']->profile?->avatarUrl())
                                    <img src="{{ $column['user']->profile->avatarUrl() }}" class="size-full object-cover">
                                @else
                                    <div class="flex size-full items-center justify-center text-stone-500">
                                        <flux:icon.user class="size-4" />
                                    </div>
                                @endif
                            </a>
                            <a href="{{ route('profile.show', $column['user']) }}" wire:navigate class="text-sm font-medium text-stone-900 hover:underline dark:text-white">{{ $column['user']->name }}</a>
                        </div>

                        @if ($column['body'])
                            <p class="mt-3 whitespace-pre-line text-sm text-stone-700 dark:text-stone-300">{{ $column['body'] }}</p>

                            @include('partials.media-grid', ['media' => $column['media']])
                        @elseif ($viewerSide === $column['side'])
                            <flux:button size="sm" variant="ghost" class="mt-3" wire:click="startSide({{ $post->id }})">
                                {{ __('Add your side') }}
                            </flux:button>
                        @else
                            <p class="mt-3 text-sm italic text-stone-400 dark:text-stone-500">
                                {{ __('Waiting for :name to add their side.', ['name' => $column['user']->name]) }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        @if ($viewerSide)
            <flux:modal name="bridge-post-side-{{ $post->id }}" class="max-w-lg">
                <form wire:submit="submitSide" class="space-y-4">
                    <flux:heading size="lg">{{ __('Add your side') }}</flux:heading>
                    <flux:subheading>{{ __('Bridge Post') }} — {{ $post->theme }}</flux:subheading>

                    <flux:textarea wire:model="sideBody" rows="4" placeholder="{{ __('Your side of the story…') }}" />

                    @include('partials.photo-picker', ['photos' => $sidePhotos, 'property' => 'sidePhotos', 'removeMethod' => 'removeSidePhoto', 'max' => 5])

                    @error('sideBody') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <div class="flex items-center justify-end gap-2">
                        <flux:button type="button" variant="ghost" wire:click="cancelSide">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary" color="cyan" wire:loading.attr="disabled" wire:target="submitSide">
                            {{ __('Post my side') }}
                        </flux:button>
                    </div>
                </form>
            </flux:modal>
        @endif
    @endforeach

    @if ($this->hasMore)
        <div wire:intersect="loadMore" wire:key="bridge-posts-load-more" class="flex justify-center py-4">
            <flux:icon.loading class="size-5 text-stone-400" />
        </div>
    @endif
</div>
