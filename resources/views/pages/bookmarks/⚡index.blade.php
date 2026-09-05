<?php

use App\Models\Bookmark;
use App\Models\CommunityPost;
use App\Models\WallPost;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Bookmarks')] class extends Component {
    use WithPagination;

    #[On('bookmark-toggled')]
    public function refresh(): void
    {
        //
    }

    public function with(): array
    {
        $bookmarks = Bookmark::query()
            ->where('user_id', Auth::id())
            ->with(['bookmarkable' => function ($morphTo) {
                $morphTo->morphWith([
                    WallPost::class => ['user.profile', 'media'],
                    CommunityPost::class => ['user.profile', 'media', 'community'],
                ]);
            }])
            ->latest()
            ->paginate(10)
            ->through(fn ($bookmark) => $bookmark->bookmarkable);

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
                            <div class="font-medium text-stone-900 dark:text-white">
                                {{ $post->user->name }}
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
                    <livewire:pages::shared.reactions :reactable="$post" :key="'bookmarked-reactions-'.get_class($post).'-'.$post->id" />
                    <livewire:pages::shared.comments :commentable="$post" :key="'bookmarked-comments-'.get_class($post).'-'.$post->id" />
                    <livewire:pages::shared.bookmark :bookmarkable="$post" :key="'bookmarked-bookmark-'.get_class($post).'-'.$post->id" />
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
                <flux:text>{{ __("You haven't bookmarked anything yet.") }}</flux:text>
            </div>
        @endforelse
    </div>

    {{ $bookmarks->links() }}
</div>
