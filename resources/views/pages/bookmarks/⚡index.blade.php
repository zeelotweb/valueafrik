<?php

use App\Models\Bookmark;
use App\Models\CommunityPost;
use App\Models\WallPost;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Bookmarks')] class extends Component {
    public int $perPage = 10;

    public int $loaded = 10;

    #[On('bookmark-toggled')]
    public function refresh(): void
    {
        // A bookmark removed from this same page (or another tab) should
        // drop out of the list immediately rather than waiting for a full
        // reload — the underlying window is now cached per-request via
        // #[Computed], which the old plain query in with() never was.
        unset($this->bookmarksWindow, $this->hasMore);
    }

    public function loadMore(): void
    {
        if ($this->hasMore) {
            $this->loaded += $this->perPage;

            unset($this->bookmarksWindow, $this->hasMore);
        }
    }

    #[Computed]
    public function bookmarksWindow()
    {
        return Bookmark::query()
            ->where('user_id', Auth::id())
            ->with(['bookmarkable' => function ($morphTo) {
                $morphTo->morphWith([
                    WallPost::class => ['user.profile', 'media', 'hashtags', 'mentions.user'],
                    CommunityPost::class => ['user.profile', 'media', 'community', 'hashtags', 'mentions.user'],
                ]);
            }])
            ->latest()
            ->limit($this->loaded + 1)
            ->get();
    }

    #[Computed]
    public function hasMore(): bool
    {
        return $this->bookmarksWindow->count() > $this->loaded;
    }

    public function with(): array
    {
        // A mixed WallPost/CommunityPost collection — loadCount/loadExists
        // batch per model class (one query per class per aggregate) instead
        // of each nested reactions/comments/bookmark component querying
        // per post. filter() drops any dangling bookmark whose target post
        // was deleted (rendered as null and skipped in the template).
        $bookmarks = $this->bookmarksWindow
            ->take($this->loaded)
            ->map(fn ($bookmark) => $bookmark->bookmarkable)
            ->filter();

        $bookmarks
            ->loadCount(['reactions', 'comments'])
            ->loadExists([
                'reactions as user_reacted' => fn ($q) => $q->where('user_id', Auth::id()),
                'bookmarks as user_bookmarked' => fn ($q) => $q->where('user_id', Auth::id()),
            ]);

        return ['bookmarks' => $bookmarks];
    }
}; ?>

<div class="mx-auto w-full max-w-2xl">
    <flux:heading size="xl">{{ __('Bookmarks') }}</flux:heading>
    <flux:subheading>{{ __('Posts you saved for later — only visible to you.') }}</flux:subheading>

    <div class="mt-6 space-y-4">
        @forelse ($bookmarks as $post)
            @continue(! $post)

            @php $isCommunityPost = $post instanceof \App\Models\CommunityPost; @endphp

            <div class="rounded-xl bg-white border border-stone-200 p-4 dark:bg-stone-900 dark:border-stone-800" wire:key="bookmarked-{{ get_class($post) }}-{{ $post->id }}">
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
                            <div class="font-medium text-stone-900 dark:text-white">
                                <a href="{{ route('profile.show', $post->user) }}" wire:navigate class="hover:underline">{{ $post->user->name }}</a>
                                @if ($isCommunityPost)
                                    <span class="font-normal text-stone-500 dark:text-stone-400">{{ __('in') }}</span>
                                    <a href="{{ route('communities.show', $post->community) }}" wire:navigate class="text-cyan-600 hover:text-cyan-500 dark:text-cyan-400">
                                        {{ $post->community->name }}
                                    </a>
                                @endif
                            </div>
                            <div class="text-xs text-stone-500 dark:text-stone-400">{{ $post->created_at->diffForHumans() }}</div>
                        </div>
                    </div>

                    <a href="{{ $isCommunityPost ? route('communities.show', $post->community) : route('profile.show', $post->user) }}" wire:navigate>
                        <flux:button size="sm" variant="ghost" icon="arrow-top-right-on-square" />
                    </a>
                </div>

                @if ($post->body)
                    <p class="mt-3 whitespace-pre-line text-stone-700 dark:text-stone-300">{!! \App\Support\RichText::render($post->body, $post->hashtags, $post->mentions) !!}</p>
                @endif

                @include('partials.media-grid', ['media' => $post->media])

                <div class="mt-3 flex items-center justify-between border-t border-stone-200 pt-2 dark:border-stone-800">
                    <div class="flex items-center gap-1">
                        <livewire:pages::shared.emoji-reactions :reactable="$post" :key="'bookmarked-emoji-'.get_class($post).'-'.$post->id" />
                        <livewire:pages::shared.comments :commentable="$post" :key="'bookmarked-comments-'.get_class($post).'-'.$post->id" />
                    </div>
                    <div class="flex items-center gap-1">
                        <livewire:pages::shared.bookmark :bookmarkable="$post" :key="'bookmarked-bookmark-'.get_class($post).'-'.$post->id" />
                        <livewire:pages::shared.reactions :reactable="$post" :key="'bookmarked-reactions-'.get_class($post).'-'.$post->id" />
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
                <flux:text>{{ __("You haven't bookmarked anything yet.") }}</flux:text>
            </div>
        @endforelse
    </div>

    @if ($this->hasMore)
        <div wire:intersect="loadMore" wire:key="bookmarks-load-more" class="flex justify-center py-4">
            <flux:icon.loading class="size-5 text-stone-400" />
        </div>
    @endif
</div>
