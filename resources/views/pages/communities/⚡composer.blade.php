<?php

use App\Models\Community;
use App\Services\ImageOptimizer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public Community $community;
    public string $body = '';

    public ?int $editingPostId = null;

    #[On('community-membership-changed')]
    public function refresh(): void
    {
        //
    }

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile[] */
    public array $photos = [];

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);

        $this->photos = array_values($this->photos);
    }

    /**
     * Requested from the community post list — scoping to this community
     * and the authenticated user's own post is the authorization check.
     */
    #[On('edit-community-post')]
    public function loadForEdit(int $postId): void
    {
        $post = $this->community->posts()
            ->where('user_id', Auth::id())
            ->findOrFail($postId);

        $this->editingPostId = $post->id;
        $this->body = $post->body ?? '';
        $this->photos = [];

        $this->modal('community-composer-'.$this->community->id)->show();
    }

    public function cancel(): void
    {
        $this->reset(['body', 'photos', 'editingPostId']);

        $this->modal('community-composer-'.$this->community->id)->close();
    }

    public function post(): void
    {
        if ($this->editingPostId) {
            $post = $this->community->posts()
                ->where('user_id', Auth::id())
                ->findOrFail($this->editingPostId);

            $this->validate([
                'body' => ['nullable', 'string', 'max:5000'],
            ]);

            if (blank($this->body) && $post->media->isEmpty()) {
                $this->addError('body', __('Write something or add a photo.'));

                return;
            }

            $post->update([
                'body' => $this->body !== '' ? $this->body : null,
                'edited_at' => now(),
            ]);

            $this->reset(['body', 'photos', 'editingPostId']);

            $this->modal('community-composer-'.$this->community->id)->close();

            $this->dispatch('community-post-created');

            return;
        }

        abort_unless($this->community->canPost(Auth::user()), 403);

        $this->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'photos' => ['array', 'max:4'],
            'photos.*' => ['image', 'max:8192'],
        ]);

        if (blank($this->body) && empty($this->photos)) {
            $this->addError('body', __('Write something or add a photo.'));

            return;
        }

        $post = $this->community->posts()->create([
            'user_id' => Auth::id(),
            'body' => $this->body !== '' ? $this->body : null,
        ]);

        Auth::user()->awardBridgeScore('community_post', $post);

        foreach ($this->photos as $photo) {
            $optimized = ImageOptimizer::store($photo, 'community-media', 'public');

            $post->media()->create([
                'user_id' => Auth::id(),
                'disk' => 'public',
                'type' => 'image',
                ...$optimized,
            ]);
        }

        $this->reset(['body', 'photos']);

        $this->modal('community-composer-'.$this->community->id)->close();

        $this->dispatch('community-post-created');
    }
}; ?>

<div>
    @if ($community->canPost(Auth::user()))
        <flux:modal name="community-composer-{{ $community->id }}" class="max-w-lg" wire:close="cancel">
            <form wire:submit="post" class="space-y-4">
                <flux:heading size="lg">{{ $editingPostId ? __('Edit post') : __('Post to :name', ['name' => $community->name]) }}</flux:heading>

                <flux:textarea
                    wire:model="body"
                    placeholder="{{ __('Share something with the community...') }}"
                    rows="4"
                />

                @if (! $editingPostId)
                    @include('partials.photo-picker', ['photos' => $photos, 'property' => 'photos', 'removeMethod' => 'removePhoto', 'max' => 4])
                @endif

                @error('body') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                <div class="flex items-center justify-end gap-2">
                    <flux:button type="button" wire:click="cancel" variant="ghost">{{ __('Cancel') }}</flux:button>

                    <flux:button type="submit" variant="primary" color="cyan" wire:loading.attr="disabled" wire:target="post">
                        {{ $editingPostId ? __('Save') : __('Post') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
