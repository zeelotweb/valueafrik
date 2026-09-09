<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $body = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile[] */
    public array $photos = [];

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);

        $this->photos = array_values($this->photos);
    }

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

        $this->modal('wall-composer')->close();

        $this->dispatch('wall-post-created');
    }
}; ?>

<flux:modal name="wall-composer" class="max-w-lg">
    <form wire:submit="post" class="space-y-4">
        <flux:heading size="lg">{{ __('Post to your wall') }}</flux:heading>

        <flux:textarea
            wire:model="body"
            placeholder="{{ __('Share something on your wall...') }}"
            rows="4"
        />

        @include('partials.photo-picker', ['photos' => $photos, 'property' => 'photos', 'removeMethod' => 'removePhoto', 'max' => 4])

        @error('body') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <div class="flex items-center justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button type="submit" variant="primary" color="cyan" wire:loading.attr="disabled" wire:target="post">
                {{ __('Post') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
