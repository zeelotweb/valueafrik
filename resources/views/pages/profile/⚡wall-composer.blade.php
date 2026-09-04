<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $body = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile[] */
    public array $photos = [];

    public function post(): void
    {
        $this->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'photos' => ['array', 'max:4'],
            'photos.*' => ['image', 'max:8192'],
        ]);

        if (blank($this->body) && empty($this->photos)) {
            $this->addError('body', __('Write something or add a photo.'));

            return;
        }

        $post = Auth::user()->wallPosts()->create([
            'body' => $this->body !== '' ? $this->body : null,
        ]);

        Auth::user()->awardBridgeScore('wall_post', $post);

        foreach ($this->photos as $photo) {
            $post->media()->create([
                'user_id' => Auth::id(),
                'disk' => 'public',
                'path' => $photo->store('wall-media', 'public'),
                'mime_type' => $photo->getMimeType(),
                'type' => 'image',
                'size' => $photo->getSize(),
            ]);
        }

        $this->reset(['body', 'photos']);

        $this->dispatch('wall-post-created');
    }
}; ?>

<div class="mb-6 rounded-xl border border-stone-200 p-4 dark:border-stone-800">
    <form wire:submit="post">
        <flux:textarea
            wire:model="body"
            placeholder="{{ __('Share something on your wall...') }}"
            rows="3"
        />

        @if ($photos)
            <div class="mt-3 grid grid-cols-4 gap-2">
                @foreach ($photos as $photo)
                    <img src="{{ $photo->temporaryUrl() }}" class="aspect-square w-full rounded-lg object-cover">
                @endforeach
            </div>
        @endif

        <div class="mt-3 flex items-center justify-between">
            <label class="cursor-pointer text-sm font-medium text-cyan-600 hover:text-cyan-500 dark:text-cyan-400">
                <input type="file" wire:model="photos" multiple accept="image/*" class="hidden">
                {{ __('Add photos') }}
            </label>

            <flux:button type="submit" variant="primary" color="cyan" wire:loading.attr="disabled">
                {{ __('Post') }}
            </flux:button>
        </div>

        @error('body') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('photos') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('photos.*') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </form>
</div>
