<?php

use App\Models\BridgePost;
use App\Models\CommunityPost;
use App\Models\LiveSession;
use App\Models\WallPost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * A personalized feed — what the people you follow have been up to, not
 * the sitewide firehose. Live streams from people you follow are pinned
 * above everything else since they're time-sensitive; the rest is a
 * merged, recency-sorted mix of wall posts, community posts, and bridge
 * posts. Community posts are filtered through Community::canView() so a
 * private community someone followed doesn't leak into a feed they
 * shouldn't see.
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
            ->with('user.profile')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($post) => [
                'type' => 'wall_post',
                'timestamp' => $post->created_at,
                'user' => $post->user,
                'excerpt' => $post->body ? Str::limit($post->body, 90) : __('shared a photo.'),
                'url' => route('profile.show', $post->user),
            ]);

        $communityPosts = CommunityPost::query()
            ->whereIn('user_id', $followingIds)
            ->with(['user.profile', 'community'])
            ->latest()
            ->limit(10)
            ->get()
            ->filter(fn ($post) => $post->community->canView($viewer))
            ->map(fn ($post) => [
                'type' => 'community_post',
                'timestamp' => $post->created_at,
                'user' => $post->user,
                'community' => $post->community,
                'excerpt' => $post->body ? Str::limit($post->body, 90) : __('shared a photo.'),
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
            ->take(8)
            ->values();

        return [
            'liveFriends' => $liveFriends,
            'items' => $items,
            'isFollowingAnyone' => true,
        ];
    }
}; ?>

<div class="space-y-3" wire:key="dashboard-following-activity" wire:poll.60s>
    @if ($liveFriends->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            @foreach ($liveFriends as $stream)
                <a
                    href="{{ route('live.show', $stream) }}"
                    wire:navigate
                    wire:key="following-live-{{ $stream->id }}"
                    class="flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 py-1.5 pe-3 ps-1.5 hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950/40 dark:hover:bg-rose-950"
                >
                    <div class="relative size-8 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                        @if ($stream->host->profile?->avatarUrl())
                            <img src="{{ $stream->host->profile->avatarUrl() }}" class="size-full object-cover">
                        @else
                            <div class="flex size-full items-center justify-center text-stone-500">
                                <flux:icon.user class="size-4" />
                            </div>
                        @endif
                        <span class="absolute right-0 top-0 size-2 rounded-full bg-rose-500 ring-2 ring-rose-50 dark:ring-rose-950"></span>
                    </div>
                    <span class="truncate text-sm font-medium text-rose-700 dark:text-rose-300">{{ $stream->host->name }}</span>
                    <span class="shrink-0 text-xs font-semibold uppercase tracking-wide text-rose-600 dark:text-rose-400">{{ __('Live') }}</span>
                </a>
            @endforeach
        </div>
    @endif

    @forelse ($items as $item)
        @if ($item['type'] === 'bridge_post')
            <a
                href="{{ $item['url'] }}"
                wire:navigate
                wire:key="following-activity-bridge-{{ $item['initiator']->id }}-{{ $item['timestamp'] }}"
                class="flex items-center gap-3 rounded-xl border border-cyan-200 p-3 hover:bg-cyan-50/50 dark:border-cyan-900 dark:hover:bg-cyan-950/20"
            >
                <div class="flex -space-x-2">
                    @foreach ([$item['initiator'], $item['partner']] as $person)
                        <div class="size-8 shrink-0 overflow-hidden rounded-full border-2 border-white bg-stone-200 dark:border-stone-900 dark:bg-stone-700">
                            @if ($person->profile?->avatarUrl())
                                <img src="{{ $person->profile->avatarUrl() }}" class="size-full object-cover">
                            @else
                                <div class="flex size-full items-center justify-center text-stone-500">
                                    <flux:icon.user class="size-4" />
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-stone-900 dark:text-white">
                        {{ $item['initiator']->name }} &amp; {{ $item['partner']->name }}
                    </p>
                    <p class="truncate text-xs text-cyan-700 dark:text-cyan-400">{{ __('started a Bridge Post on') }} {{ $item['theme'] }}</p>
                </div>

                <flux:icon.arrows-right-left class="size-4 shrink-0 text-cyan-600 dark:text-cyan-400" />
            </a>
        @else
            <a
                href="{{ $item['url'] }}"
                wire:navigate
                wire:key="following-activity-{{ $item['type'] }}-{{ $item['user']->id }}-{{ $item['timestamp'] }}"
                class="flex items-center gap-3 rounded-xl bg-white border border-stone-200 p-3 hover:bg-stone-50 dark:bg-stone-900 dark:border-stone-800 dark:hover:bg-stone-800"
            >
                <div class="size-9 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                    @if ($item['user']->profile?->avatarUrl())
                        <img src="{{ $item['user']->profile->avatarUrl() }}" class="size-full object-cover">
                    @else
                        <div class="flex size-full items-center justify-center text-stone-500">
                            <flux:icon.user class="size-4" />
                        </div>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm text-stone-900 dark:text-white">
                        <span class="font-medium">{{ $item['user']->name }}</span>
                        @if ($item['type'] === 'community_post')
                            {{ __('posted in') }} <span class="font-medium">{{ $item['community']->name }}</span>
                        @else
                            {{ __('posted to their Wall') }}
                        @endif
                    </p>
                    <p class="truncate text-xs text-stone-500 dark:text-stone-400">{{ $item['excerpt'] }}</p>
                </div>

                <flux:icon.chevron-right class="size-4 shrink-0 text-stone-400" />
            </a>
        @endif
    @empty
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
    @endforelse
</div>
