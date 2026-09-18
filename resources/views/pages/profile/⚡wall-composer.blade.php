<?php

use App\Models\WallPost;
use App\Services\ImageOptimizer;
use App\Support\RichText;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $body = '';

    public string $visibility = WallPost::VISIBILITY_PUBLIC;

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
        $this->visibility = $post->visibility;
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
        $this->reset(['body', 'visibility', 'photos', 'editingPostId']);

        $this->modal('wall-composer')->close();
    }

    public function post(): void
    {
        $this->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'visibility' => ['required', 'in:public,followers_only,private'],
            'photos' => ['array', 'max:5'],
            // 6000x6000 caps the actual memory cost of decoding this on the
            // way in (ImageOptimizer::MIN_MEMORY_LIMIT_BYTES is sized against
            // this same ceiling) — the file-size cap alone doesn't guarantee
            // that, since compression ratio varies by photo.
            'photos.*' => ['image', 'max:20480', Rule::dimensions()->maxWidth(6000)->maxHeight(6000)],
        ]);

        if ($this->editingPostId) {
            $post = Auth::user()->wallPosts()->findOrFail($this->editingPostId);

            if (blank($this->body) && $post->media->isEmpty()) {
                $this->addError('body', __('Write something or add a photo.'));

                return;
            }

            $post->update([
                'body' => $this->body !== '' ? $this->body : null,
                'visibility' => $this->visibility,
                'edited_at' => now(),
            ]);

            RichText::syncHashtags($post, $this->body);
            RichText::syncMentions($post, $this->body, Auth::user());

            $this->reset(['body', 'visibility', 'photos', 'editingPostId']);

            $this->modal('wall-composer')->close();

            $this->dispatch('wall-post-created');

            Flux::toast(variant: 'success', text: __('Post updated.'));

            return;
        }

        if (blank($this->body) && empty($this->photos)) {
            $this->addError('body', __('Write something or add a photo.'));

            return;
        }

        $post = Auth::user()->wallPosts()->create([
            'body' => $this->body !== '' ? $this->body : null,
            'visibility' => $this->visibility,
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

        $this->reset(['body', 'visibility', 'photos']);

        $this->modal('wall-composer')->close();

        $this->dispatch('wall-post-created');

        Flux::toast(variant: 'success', text: __('Posted to your wall.'));
    }
}; ?>

{{--
    Full screen below md (edge to edge, no floating card in a sea of
    backdrop on a phone or small tablet) — h-dvh/w-full/rounded-none apply
    at every size as the base, so there's nothing to "turn off" between
    breakpoints. From md up it becomes a right-anchored drawer instead of
    a centered card: full height, capped at half the viewport width,
    flush against the screen's trailing edge. That's explicit inset
    positioning (end-0/start-auto), not the dialog's default centering
    margin — trying to constrain width while leaving the UA's own
    margin:auto centering in place would just re-center a narrower box
    instead of pinning it to the edge, so md:m-0 removes it outright.

    p-0! zeroes the dialog's own padding at every size — Flux's default
    otherwise still applies its own p-6 fallback in the sm-to-md gap (and
    p-4 below sm), leaving a visible inset border around what's supposed
    to be edge-to-edge. Padding lives on the form below instead, which
    also keeps it off the ✕ close button positioned relative to this same
    dialog.
--}}
<flux:modal
    name="wall-composer"
    class="h-dvh max-h-none w-full max-w-none rounded-none p-0! md:inset-y-0 md:end-0 md:start-auto md:m-0 md:w-[50vw] md:rounded-s-2xl"
    wire:close="cancel"
>
    <form wire:submit="post" class="flex h-full flex-col p-4 md:p-6">
        <div class="flex items-center gap-3 pb-5">
            <flux:avatar size="lg" circle :src="Auth::user()->profile?->avatarUrl()" />
            <div class="min-w-0">
                <flux:heading size="lg">{{ $editingPostId ? __('Edit post') : __('Post to your wall') }}</flux:heading>
                <flux:text size="sm" class="truncate text-stone-500 dark:text-stone-400">{{ Auth::user()->name }}</flux:text>
            </div>
        </div>

        <div class="flex-1 space-y-4 overflow-y-auto">
            @include('partials.mention-textarea', ['wireModel' => 'body', 'placeholder' => __('Share something on your wall...'), 'rows' => 'auto'])

            @if (! $editingPostId)
                @include('partials.photo-picker', ['photos' => $photos, 'property' => 'photos', 'removeMethod' => 'removePhoto', 'max' => 5])

                <ul class="space-y-2 text-sm text-stone-500 dark:text-stone-400">
                    <li class="flex gap-2">
                        <flux:icon.camera class="size-4 shrink-0 translate-y-0.5 text-stone-400 dark:text-stone-500" />
                        <span>{{ __('Share what\'s real — a real photo means more here than a polished one.') }}</span>
                    </li>
                    <li class="flex gap-2">
                        <flux:icon.users class="size-4 shrink-0 translate-y-0.5 text-stone-400 dark:text-stone-500" />
                        <span>{{ __('If someone else is in the photo, share only what you\'d be comfortable them seeing shared.') }}</span>
                    </li>
                    <li class="flex gap-2">
                        <flux:icon.eye class="size-4 shrink-0 translate-y-0.5 text-stone-400 dark:text-stone-500" />
                        <span>{{ __('Nothing here is ranked or boosted — whoever you choose above sees it, and no one else.') }}</span>
                    </li>
                </ul>
            @endif

            @error('body') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-stone-200 pt-4 dark:border-stone-800">
            <flux:select wire:model="visibility" size="sm" class="w-auto!">
                <flux:select.option value="public">{{ __('🌍 Public') }}</flux:select.option>
                <flux:select.option value="followers_only">{{ __('👥 Followers only') }}</flux:select.option>
                <flux:select.option value="private">{{ __('🔒 Only me') }}</flux:select.option>
            </flux:select>

            <div class="flex items-center gap-2">
                <flux:button type="button" wire:click="cancel" variant="ghost">{{ __('Cancel') }}</flux:button>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="post" class="btn-flat-primary">
                    {{ $editingPostId ? __('Save') : __('Post') }}
                </flux:button>
            </div>
        </div>
    </form>
</flux:modal>
