<?php

use App\Models\Community;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public Community $community;
    public string $body = '';

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

    public function post(): void
    {
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
            $post->media()->create([
                'user_id' => Auth::id(),
                'disk' => 'public',
                'path' => $photo->store('community-media', 'public'),
                'mime_type' => $photo->getMimeType(),
                'type' => 'image',
                'size' => $photo->getSize(),
            ]);
        }

        $this->reset(['body', 'photos']);

        $this->dispatch('community-post-created');
    }
}; ?>

<div>
    @if ($community->canPost(Auth::user()))
        <div class="mb-6 rounded-xl bg-white border border-stone-200 p-4 dark:bg-stone-900 dark:border-stone-800">
            <form wire:submit="post">
                <flux:textarea
                    wire:model="body"
                    placeholder="{{ __('Share something with the community...') }}"
                    rows="3"
                />

                @include('partials.photo-picker', ['photos' => $photos, 'property' => 'photos', 'removeMethod' => 'removePhoto', 'max' => 4])

                <div class="mt-3 flex items-center justify-end">
                    <flux:button type="submit" variant="primary" color="cyan" wire:loading.attr="disabled" wire:target="post">
                        {{ __('Post') }}
                    </flux:button>
                </div>

                @error('body') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </form>
        </div>
    @endif
</div>
