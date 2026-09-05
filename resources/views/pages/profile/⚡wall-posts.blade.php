<?php

use App\Models\User;
use App\Models\WallPost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public User $user;

    #[On('wall-post-created')]
    public function refresh(): void
    {
        $this->resetPage();
    }

    public function delete(int $postId): void
    {
        $post = WallPost::findOrFail($postId);

        abort_if($post->user_id !== Auth::id(), 403);

        foreach ($post->media as $media) {
            Storage::disk($media->disk)->delete($media->path);
        }

        $post->delete();
    }

    public function with(): array
    {
        return [
            'posts' => $this->user->wallPosts()
                ->with(['user.profile', 'media'])
                ->latest()
                ->paginate(10),
        ];
    }
}; ?>

<div class="space-y-4" wire:key="wall-posts-{{ $user->id }}">
    @forelse ($posts as $post)
        <div class="rounded-xl bg-white border border-stone-200 p-4 dark:bg-stone-900 dark:border-stone-800" wire:key="wall-post-{{ $post->id }}">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="size-10 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                        @if ($post->user->profile?->avatarUrl())
                            <img src="{{ $post->user->profile->avatarUrl() }}" class="size-full object-cover">
                        @else
                            <div class="flex size-full items-center justify-center text-stone-500">
                                <flux:icon.user class="size-5" />
                            </div>
                        @endif
                    </div>
                    <div>
                        <div class="font-medium text-stone-900 dark:text-white">{{ $post->user->name }}</div>
                        <div class="text-xs text-stone-500 dark:text-stone-400">{{ $post->created_at->diffForHumans() }}</div>
                    </div>
                </div>

                @if (Auth::id() === $post->user_id)
                    <flux:button
                        size="sm"
                        variant="ghost"
                        wire:click="delete({{ $post->id }})"
                        wire:confirm="{{ __('Delete this post?') }}"
                    >
                        <flux:icon.trash class="size-4" />
                    </flux:button>
                @endif
            </div>

            @if ($post->body)
                <p class="mt-3 whitespace-pre-line text-stone-700 dark:text-stone-300">{{ $post->body }}</p>
            @endif

            @if ($post->media->isNotEmpty())
                <div class="mt-3 grid grid-cols-2 gap-2">
                    @foreach ($post->media as $media)
                        <img src="{{ $media->url() }}" class="aspect-square w-full rounded-lg object-cover">
                    @endforeach
                </div>
            @endif

            <div class="mt-3 flex items-start gap-1 border-t border-stone-200 pt-2 dark:border-stone-800">
                <livewire:pages::shared.reactions :reactable="$post" :key="'wall-post-reactions-'.$post->id" />
                <livewire:pages::shared.comments :commentable="$post" :key="'wall-post-comments-'.$post->id" />
            </div>
        </div>
    @empty
        <div class="rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
            <flux:text>{{ __('No wall posts yet.') }}</flux:text>
        </div>
    @endforelse

    {{ $posts->links() }}
</div>
