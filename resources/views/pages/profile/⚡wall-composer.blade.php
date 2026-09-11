<?php

use App\Models\WallPost;
use App\Services\ImageOptimizer;
use App\Support\RichText;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $body = '';

    public ?int $editingPostId = null;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile[] */
    public array $photos = [];

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);

        $this->photos = array_values($this->photos);
    }

    /**
     * Requested from the wall post list — scoping the lookup to the
     * authenticated user's own posts is the authorization check: a
     * mismatched ID 404s rather than silently loading someone else's post.
     */
    #[On('edit-wall-post')]
    public function loadForEdit(int $postId): void
    {
        $post = Auth::user()->wallPosts()->findOrFail($postId);

        $this->editingPostId = $post->id;
        $this->body = $post->body ?? '';
        $this->photos = [];

        $this->modal('wall-composer')->show();
    }

    /**
     * Wired to the modal's own wire:close, so every way of dismissing it
     * (Cancel, the built-in ✕, Escape, backdrop click) resets state the
     * same way — otherwise a stale editingPostId/body could leak into the
     * next time this same component is opened to create a new post.
     */
    public function cancel(): void
    {
        $this->reset(['body', 'photos', 'editingPostId']);

        $this->modal('wall-composer')->close();
    }

    public function post(): void
    {
        $this->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'photos' => ['array', 'max:5'],
            'photos.*' => ['image', 'max:8192'],
        ]);

        if ($this->editingPostId) {
            $post = Auth::user()->wallPosts()->findOrFail($this->editingPostId);

            if (blank($this->body) && $post->media->isEmpty()) {
                $this->addError('body', __('Write something or add a photo.'));

                return;
            }

            $post->update([
                'body' => $this->body !== '' ? $this->body : null,
                'edited_at' => now(),
            ]);

            RichText::syncHashtags($post, $this->body);
            RichText::syncMentions($post, $this->body, Auth::user());

            $this->reset(['body', 'photos', 'editingPostId']);

            $this->modal('wall-composer')->close();

            $this->dispatch('wall-post-created');

            return;
        }

        if (blank($this->body) && empty($this->photos)) {
            $this->addError('body', __('Write something or add a photo.'));

            return;
        }

        $post = Auth::user()->wallPosts()->create([
            'body' => $this->body !== '' ? $this->body : null,
        ]);

        Auth::user()->awardBridgeScore('wall_post', $post);

        RichText::syncHashtags($post, $this->body);
        RichText::syncMentions($post, $this->body, Auth::user());

        foreach ($this->photos as $photo) {
            $optimized = ImageOptimizer::store($photo, 'wall-media', 'public');

            $post->media()->create([
                'user_id' => Auth::id(),
                'disk' => 'public',
                'type' => 'image',
                ...$optimized,
            ]);
        }

        $this->reset(['body', 'photos']);

        $this->modal('wall-composer')->close();

        $this->dispatch('wall-post-created');
    }
}; ?>

<flux:modal name="wall-composer" class="max-w-lg" wire:close="cancel">
    <form wire:submit="post" class="space-y-4">
        <flux:heading size="lg">{{ $editingPostId ? __('Edit post') : __('Post to your wall') }}</flux:heading>

        @include('partials.mention-textarea', ['wireModel' => 'body', 'placeholder' => __('Share something on your wall...'), 'rows' => 4])

        @if (! $editingPostId)
            @include('partials.photo-picker', ['photos' => $photos, 'property' => 'photos', 'removeMethod' => 'removePhoto', 'max' => 5])
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
