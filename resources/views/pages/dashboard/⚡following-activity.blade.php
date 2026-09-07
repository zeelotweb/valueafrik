<?php

use App\Models\BridgePost;
use App\Models\CommunityPost;
use App\Models\LiveSession;
use App\Models\WallPost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * A personalized, horizontally-scrolling gallery of what the people you
 * follow have been up to — not the sitewide firehose. Live streams from
 * people you follow lead the row since they're time-sensitive; the rest
 * is a merged, recency-sorted mix of wall posts, community posts, and
 * bridge posts, each carrying its own photo when there is one. Community
 * posts are filtered through Community::canView() so a private community
 * someone followed doesn't leak into a feed they shouldn't see.
 */
new class extends Component {
    public function with(): array
    {
        $viewer = Auth::user();
        $followingIds = $viewer->following()->pluck('users.id');

        if ($followingIds->isEmpty()) {
            return ['liveFriends' => collect(), 'items' => collect(), 'isFollowingAnyone' => false];
        }

        $liveFriends = LiveSession::query()
            ->where('type', LiveSession::TYPE_STREAM)
            ->where('status', LiveSession::STATUS_LIVE)
            ->whereIn('host_id', $followingIds)
            ->with('host.profile')
            ->latest('started_at')
            ->get();

        $wallPosts = WallPost::query()
            ->whereIn('user_id', $followingIds)
            ->with(['user.profile', 'media'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($post) => [
                'type' => 'wall_post',
                'timestamp' => $post->created_at,
                'user' => $post->user,
                'photo' => $post->media->first()?->url(),
                'excerpt' => $post->body ? Str::limit($post->body, 110) : __('Shared a photo.'),
                'url' => route('profile.show', $post->user),
            ]);

        $communityPosts = CommunityPost::query()
            ->whereIn('user_id', $followingIds)
            ->with([
                'user.profile',
                'media',
                // Scoped to just the viewer's own membership row (at most
                // one) so canView()'s membershipFor() check below reads it
                // from memory instead of firing a query per post.
                'community.members' => fn ($q) => $q->whereKey($viewer->id),
            ])
            ->latest()
            ->limit(10)
            ->get()
            ->filter(fn ($post) => $post->community->canView($viewer))
            ->map(fn ($post) => [
                'type' => 'community_post',
                'timestamp' => $post->created_at,
                'user' => $post->user,
                'community' => $post->community,
                'photo' => $post->media->first()?->url(),
                'excerpt' => $post->body ? Str::limit($post->body, 110) : __('Shared a photo.'),
                'url' => route('communities.show', $post->community),
            ]);

        $bridgePosts = BridgePost::query()
            ->where('status', BridgePost::STATUS_ACTIVE)
            ->whereNotNull('initiator_body')
            ->whereNotNull('partner_body')
            ->where(fn ($query) => $query->whereIn('initiator_id', $followingIds)->orWhereIn('partner_id', $followingIds))
            ->with(['initiator.profile', 'partner.profile'])
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->map(fn ($post) => [
                'type' => 'bridge_post',
                'timestamp' => $post->updated_at,
                'initiator' => $post->initiator,
                'partner' => $post->partner,
                'theme' => $post->theme,
                'url' => route('profile.show', $post->initiator),
            ]);

        $items = $wallPosts->concat($communityPosts)->concat($bridgePosts)
            ->sortByDesc('timestamp')
            ->take(10)
            ->values();

        return [
            'liveFriends' => $liveFriends,
            'items' => $items,
            'isFollowingAnyone' => true,
        ];
    }
}; ?>

<div wire:key="dashboard-following-activity" wire:poll.60s>
    @if ($items->isEmpty() && $liveFriends->isEmpty())
        @if (! $isFollowingAnyone)
            <div class="rounded-xl border border-dashed border-stone-300 p-6 text-center dark:border-stone-700">
                <flux:text>{{ __("You're not following anyone yet — find people to bridge with.") }}</flux:text>
                <div class="mt-3">
                    <a href="{{ route('discover.index') }}" wire:navigate>
                        <flux:button size="sm" variant="primary" color="cyan">{{ __('Discover people') }}</flux:button>
                    </a>
                </div>
            </div>
        @else
            <div class="rounded-xl border border-dashed border-stone-300 p-6 text-center dark:border-stone-700">
                <flux:text>{{ __('No recent activity from people you follow yet.') }}</flux:text>
            </div>
        @endif
    @else
        <div
            class="group/gallery relative"
            x-data="{
                atStart: true,
                atEnd: false,
                showArrows: false,
                updateEdges() {
                    this.atStart = $refs.scroller.scrollLeft <= 0;
                    this.atEnd = $refs.scroller.scrollLeft + $refs.scroller.clientWidth >= $refs.scroller.scrollWidth - 1;
                },
                scroll(direction) {
                    $refs.scroller.scrollBy({ left: direction * 240, behavior: 'smooth' });
                },
            }"
            x-init="updateEdges()"
            @mouseenter="showArrows = true"
            @mouseleave="showArrows = false"
            @touchstart="showArrows = true"
        >
            <button
                type="button"
                x-show="showArrows && !atStart"
                x-transition.opacity
                x-cloak
                @click="scroll(-1)"
                class="absolute -left-3 top-1/2 z-10 flex size-9 -translate-y-1/2 items-center justify-center rounded-full border border-stone-200 bg-white text-stone-600 shadow-md hover:text-stone-900 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-300 dark:hover:text-white"
                aria-label="{{ __('Scroll left') }}"
            >
                <flux:icon.chevron-left class="size-4" />
            </button>

            <button
                type="button"
                x-show="showArrows && !atEnd"
                x-transition.opacity
                x-cloak
                @click="scroll(1)"
                class="absolute -right-3 top-1/2 z-10 flex size-9 -translate-y-1/2 items-center justify-center rounded-full border border-stone-200 bg-white text-stone-600 shadow-md hover:text-stone-900 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-300 dark:hover:text-white"
                aria-label="{{ __('Scroll right') }}"
            >
                <flux:icon.chevron-right class="size-4" />
            </button>

            <div
                x-ref="scroller"
                @scroll.debounce.50ms="updateEdges()"
                class="scrollbar-hide -mx-1 flex snap-x snap-mandatory gap-4 overflow-x-auto px-1 pb-3"
            >
            @foreach ($liveFriends as $stream)
                <a
                    href="{{ route('live.show', $stream) }}"
                    wire:navigate
                    wire:key="following-live-{{ $stream->id }}"
                    class="group relative flex h-72 w-56 shrink-0 snap-start flex-col overflow-hidden rounded-2xl bg-gradient-to-br from-rose-500 to-rose-700 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
                >
                    <div class="flex items-center gap-1.5 p-4">
                        <span class="relative flex size-2">
                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-white opacity-75"></span>
                            <span class="relative inline-flex size-2 rounded-full bg-white"></span>
                        </span>
                        <span class="text-xs font-bold uppercase tracking-wider text-white">{{ __('Live now') }}</span>
                    </div>

                    <div class="flex flex-1 flex-col items-center justify-center gap-3 px-4 text-center">
                        <div class="size-16 shrink-0 overflow-hidden rounded-full ring-4 ring-white/30">
                            @if ($stream->host->profile?->avatarUrl())
                                <img src="{{ $stream->host->profile->avatarUrl() }}" class="size-full object-cover">
                            @else
                                <div class="flex size-full items-center justify-center bg-rose-400 text-white">
                                    <flux:icon.user class="size-7" />
                                </div>
                            @endif
                        </div>
                        <p class="truncate text-sm font-semibold text-white">{{ $stream->host->name }}</p>
                        @if ($stream->title)
                            <p class="line-clamp-2 text-xs text-rose-100">{{ $stream->title }}</p>
                        @endif
                    </div>

                    <div class="p-4 pt-0">
                        <span class="block rounded-lg bg-white/15 py-2 text-center text-xs font-semibold text-white backdrop-blur-sm group-hover:bg-white/25">
                            {{ __('Tap to join') }}
                        </span>
                    </div>
                </a>
            @endforeach

            @foreach ($items as $item)
                @if ($item['type'] === 'bridge_post')
                    <a
                        href="{{ $item['url'] }}"
                        wire:navigate
                        wire:key="following-activity-bridge-{{ $item['initiator']->id }}-{{ $item['timestamp'] }}"
                        class="group flex h-72 w-56 shrink-0 snap-start flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-stone-800 dark:bg-stone-900"
                    >
                        <div class="relative flex h-32 shrink-0 items-center justify-center bg-gradient-to-br from-rose-100 to-amber-50 dark:from-rose-950 dark:to-stone-900">
                            <span class="absolute left-3 top-3 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-semibold text-rose-700 dark:bg-stone-900/80 dark:text-rose-400">
                                {{ __('Bridge Post') }}
                            </span>
                            <div class="flex -space-x-3">
                                @foreach ([$item['initiator'], $item['partner']] as $person)
                                    <div class="size-12 shrink-0 overflow-hidden rounded-full border-2 border-white bg-stone-200 dark:border-stone-900 dark:bg-stone-700">
                                        @if ($person->profile?->avatarUrl())
                                            <img src="{{ $person->profile->avatarUrl() }}" class="size-full object-cover">
                                        @else
                                            <div class="flex size-full items-center justify-center text-stone-500">
                                                <flux:icon.user class="size-5" />
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex flex-1 flex-col gap-1 p-4">
                            <p class="truncate text-sm font-semibold text-stone-900 dark:text-white">
                                {{ $item['initiator']->name }} &amp; {{ $item['partner']->name }}
                            </p>
                            <p class="line-clamp-3 flex-1 text-xs text-stone-500 dark:text-stone-400">
                                {{ __('Comparing') }} {{ $item['theme'] }}
                            </p>
                            <p class="text-[11px] text-stone-400 dark:text-stone-500">{{ $item['timestamp']->diffForHumans() }}</p>
                        </div>
                    </a>
                @else
                    <a
                        href="{{ $item['url'] }}"
                        wire:navigate
                        wire:key="following-activity-{{ $item['type'] }}-{{ $item['user']->id }}-{{ $item['timestamp'] }}"
                        class="group flex h-72 w-56 shrink-0 snap-start flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-stone-800 dark:bg-stone-900"
                    >
                        <div @class([
                            'relative flex h-32 shrink-0 items-center justify-center overflow-hidden',
                            'bg-gradient-to-br from-cyan-100 to-cyan-50 dark:from-cyan-950 dark:to-stone-900' => $item['type'] === 'wall_post' && ! $item['photo'],
                            'bg-gradient-to-br from-amber-100 to-amber-50 dark:from-amber-950 dark:to-stone-900' => $item['type'] === 'community_post' && ! $item['photo'],
                        ])>
                            @if ($item['photo'])
                                <img src="{{ $item['photo'] }}" class="absolute inset-0 size-full object-cover">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>
                            @else
                                <flux:icon
                                    :icon="$item['type'] === 'community_post' ? 'user-group' : 'pencil-square'"
                                    class="size-10 {{ $item['type'] === 'community_post' ? 'text-amber-300 dark:text-amber-800' : 'text-cyan-300 dark:text-cyan-800' }}"
                                />
                            @endif

                            <span @class([
                                'absolute left-3 top-3 rounded-full px-2.5 py-1 text-[11px] font-semibold',
                                'bg-white/90 dark:bg-stone-900/80' => true,
                                'text-cyan-700 dark:text-cyan-400' => $item['type'] === 'wall_post',
                                'text-amber-700 dark:text-amber-400' => $item['type'] === 'community_post',
                            ])>
                                {{ $item['type'] === 'community_post' ? $item['community']->name : __('Wall') }}
                            </span>
                        </div>

                        <div class="flex flex-1 flex-col gap-2 p-4">
                            <div class="flex items-center gap-2">
                                <div class="size-6 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                                    @if ($item['user']->profile?->avatarUrl())
                                        <img src="{{ $item['user']->profile->avatarUrl() }}" class="size-full object-cover">
                                    @else
                                        <div class="flex size-full items-center justify-center text-stone-500">
                                            <flux:icon.user class="size-3" />
                                        </div>
                                    @endif
                                </div>
                                <span class="truncate text-sm font-semibold text-stone-900 dark:text-white">{{ $item['user']->name }}</span>
                            </div>

                            <p class="line-clamp-3 flex-1 text-xs text-stone-600 dark:text-stone-400">{{ $item['excerpt'] }}</p>
                            <p class="text-[11px] text-stone-400 dark:text-stone-500">{{ $item['timestamp']->diffForHumans() }}</p>
                        </div>
                    </a>
                @endif
            @endforeach
            </div>
        </div>
    @endif
</div>
