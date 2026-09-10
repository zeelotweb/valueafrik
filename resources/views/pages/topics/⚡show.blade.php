<?php

use App\Models\CommunityPost;
use App\Models\Hashtag;
use App\Models\WallPost;
use App\Support\RichText;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Topic')] class extends Component {
    public Hashtag $hashtag;

    public function mount(Hashtag $hashtag): void
    {
        $this->hashtag = $hashtag;
    }

    public function with(): array
    {
        $eagerLoad = ['user.profile', 'media', 'hashtags', 'mentions.user'];
        $counts = ['reactions', 'comments'];
        $exists = [
            'reactions as user_reacted' => fn ($q) => $q->where('user_id', Auth::id()),
            'bookmarks as user_bookmarked' => fn ($q) => $q->where('user_id', Auth::id()),
        ];

        $wallPosts = $this->hashtag->wallPosts()
            ->with($eagerLoad)
            ->withCount($counts)
            ->withExists($exists)
            ->latest()
            ->get();

        $communityPosts = $this->hashtag->communityPosts()
            ->with([...$eagerLoad, 'community'])
            ->withCount($counts)
            ->withExists($exists)
            ->latest()
            ->get();

        $posts = $wallPosts->concat($communityPosts)->sortByDesc('created_at')->values();

        return ['posts' => $posts];
    }
}; ?>

<div class="mx-auto w-full max-w-2xl">
    <flux:heading size="xl">#{{ $hashtag->name }}</flux:heading>
    <flux:subheading>{{ trans_choice('1 post|:count posts', $posts->count()) }}</flux:subheading>

    <div class="mt-6 space-y-4">
        @forelse ($posts as $post)
            @php $isCommunityPost = $post instanceof CommunityPost; @endphp

            <div class="rounded-xl bg-white border border-stone-200 p-4 dark:bg-stone-900 dark:border-stone-800" wire:key="topic-post-{{ get_class($post) }}-{{ $post->id }}">
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
                                @if ($isCommunityPost)
                                    <a href="{{ route('communities.show', $post->community) }}" wire:navigate class="hover:underline">{{ $post->community->name }}</a>
                                    &middot;
                                @endif
                                {{ $post->created_at->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                </div>

                @if ($post->body)
                    <p class="mt-3 whitespace-pre-line text-stone-700 dark:text-stone-300">{!! RichText::render($post->body, $post->hashtags, $post->mentions) !!}</p>
                @endif

                @if ($post->media->isNotEmpty())
                    @php $mediaUrls = $post->media->map->url()->all(); @endphp
                    <div class="mt-3 grid grid-cols-2 gap-2" x-data>
                        @foreach ($post->media as $index => $media)
                            <button type="button" x-on:click="window.dispatchEvent(new CustomEvent('media-viewer:show', { detail: { images: @js($mediaUrls), index: {{ $index }} } }))" class="block">
                                <img src="{{ $media->url() }}" class="aspect-square w-full rounded-lg object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="mt-3 flex items-center justify-between border-t border-stone-200 pt-2 dark:border-stone-800">
                    <div class="flex items-center gap-1">
                        <livewire:pages::shared.emoji-reactions :reactable="$post" :key="'topic-emoji-'.get_class($post).'-'.$post->id" />
                        <livewire:pages::shared.comments :commentable="$post" :key="'topic-comments-'.get_class($post).'-'.$post->id" />
                    </div>
                    <div class="flex items-center gap-1">
                        <livewire:pages::shared.bookmark :bookmarkable="$post" :key="'topic-bookmark-'.get_class($post).'-'.$post->id" />
                        <livewire:pages::shared.reactions :reactable="$post" :key="'topic-reactions-'.get_class($post).'-'.$post->id" />
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
                <flux:text>{{ __('No posts tagged #:name yet.', ['name' => $hashtag->name]) }}</flux:text>
            </div>
        @endforelse
    </div>
</div>
