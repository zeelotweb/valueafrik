<?php

use App\Models\User;
use App\Models\WallPost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public User $user;

    public int $perPage = 10;

    public int $loaded = 10;

    #[On('wall-post-created')]
    public function refresh(): void
    {
        $this->loaded = $this->perPage;

        unset($this->postsWindow, $this->hasMore);
    }

    public function loadMore(): void
    {
        if ($this->hasMore) {
            $this->loaded += $this->perPage;

            unset($this->postsWindow, $this->hasMore);
        }
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

    /**
     * Fetches one row past the current window so hasMore can tell whether
     * there's more without a separate count() query — same pattern as the
     * notifications page's infinite scroll.
     */
    #[Computed]
    public function postsWindow()
    {
        // Eager-loaded here so the nested reactions/comments/bookmark
        // components don't each fire their own count/exists query per
        // post — see HasReactions/HasComments/HasBookmarks.
        return $this->user->wallPosts()
            ->with(['user.profile', 'media', 'hashtags', 'mentions.user'])
            ->withCount(['reactions', 'comments'])
            ->withExists([
                'reactions as user_reacted' => fn ($q) => $q->where('user_id', Auth::id()),
                'bookmarks as user_bookmarked' => fn ($q) => $q->where('user_id', Auth::id()),
            ])
            ->latest()
            ->limit($this->loaded + 1)
            ->get();
    }

    #[Computed]
    public function hasMore(): bool
    {
        return $this->postsWindow->count() > $this->loaded;
    }

    public function with(): array
    {
        return [
            'posts' => $this->postsWindow->take($this->loaded),
        ];
    }
}; ?>

<div class="space-y-4" wire:key="wall-posts-{{ $user->id }}">
    @forelse ($posts as $post)
        <div class="rounded-xl bg-white border border-stone-200 p-4 dark:bg-stone-900 dark:border-stone-800" wire:key="wall-post-{{ $post->id }}">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="{{ route('profile.show', $post->user) }}" wire:navigate class="size-10 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                        @if ($post->user->profile?->avatarUrl())
                            <img src="{{ $post->user->profile->avatarUrl() }}" class="size-full object-cover">
                        @else
                            <div class="flex size-full items-center justify-center text-stone-500">
                                <flux:icon.user class="size-5" />
                            </div>
                        @endif
                    </a>
                    <div>
                        <a href="{{ route('profile.show', $post->user) }}" wire:navigate class="font-medium text-stone-900 hover:underline dark:text-white">{{ $post->user->name }}</a>
                        <div class="text-xs text-stone-500 dark:text-stone-400">
                            {{ $post->created_at->diffForHumans() }}
                            @if ($post->edited_at)
                                &middot; {{ __('edited') }}
                            @endif
                        </div>
                    </div>
                </div>

                @if (Auth::id() === $post->user_id)
                    <div class="flex items-center gap-1">
                        <flux:button
                            size="sm"
                            variant="ghost"
                            wire:click="$dispatch('edit-wall-post', { postId: {{ $post->id }} })"
                        >
                            <flux:icon.pencil class="size-4" />
                        </flux:button>

                        <flux:button
                            size="sm"
                            variant="ghost"
                            wire:click="delete({{ $post->id }})"
                            wire:confirm="{{ __('Delete this post?') }}"
                        >
                            <flux:icon.trash class="size-4" />
                        </flux:button>
                    </div>
                @endif
            </div>

            @if ($post->body)
                <p class="mt-3 whitespace-pre-line text-stone-700 dark:text-stone-300">{!! \App\Support\RichText::render($post->body, $post->hashtags, $post->mentions) !!}</p>
            @endif

            @include('partials.media-grid', ['media' => $post->media])

            <div class="mt-3 flex items-center justify-between border-t border-stone-200 pt-2 dark:border-stone-800">
                <div class="flex items-center gap-1">
                    <livewire:pages::shared.emoji-reactions :reactable="$post" :key="'wall-post-emoji-'.$post->id" />
                    <livewire:pages::shared.comments :commentable="$post" :key="'wall-post-comments-'.$post->id" />
                </div>
                <div class="flex items-center gap-1">
                    <livewire:pages::shared.bookmark :bookmarkable="$post" :key="'wall-post-bookmark-'.$post->id" />
                    <livewire:pages::shared.reactions :reactable="$post" :key="'wall-post-reactions-'.$post->id" />
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
            <flux:text>{{ __('No wall posts yet.') }}</flux:text>
        </div>
    @endforelse

    @if ($this->hasMore)
        <div wire:intersect="loadMore" wire:key="wall-posts-load-more" class="flex justify-center py-4">
            <flux:icon.loading class="size-5 text-stone-400" />
        </div>
    @endif
</div>
