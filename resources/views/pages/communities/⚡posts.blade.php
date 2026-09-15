<?php

use App\Models\Community;
use App\Models\CommunityPost;
use App\Models\CommunityReport;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public Community $community;
    public ?int $reportingPostId = null;
    public string $reportReason = '';
    public int $perPage = 10;
    public int $loaded = 10;

    #[On('community-post-created')]
    #[On('community-membership-changed')]
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
        $post = CommunityPost::whereKey($postId)->where('community_id', $this->community->id)->firstOrFail();

        abort_unless($post->user_id === Auth::id() || $this->community->canModerate(Auth::user()), 403);

        foreach ($post->media as $media) {
            $media->deleteFiles();
        }

        $post->delete();

        unset($this->postsWindow, $this->hasMore);
    }

    public function hidePost(int $postId): void
    {
        $post = CommunityPost::whereKey($postId)->where('community_id', $this->community->id)->firstOrFail();

        $post->hideFor(Auth::user());

        unset($this->postsWindow, $this->hasMore);
    }

    public function toggleMute(int $postId): void
    {
        $post = CommunityPost::whereKey($postId)->where('community_id', $this->community->id)->firstOrFail();
        $viewer = Auth::user();

        abort_if($viewer->id === $post->user_id, 403);

        if ($viewer->hasMuted($post->user)) {
            $viewer->unmute($post->user);
        } else {
            $viewer->mute($post->user);
        }

        unset($this->postsWindow, $this->hasMore);
    }

    public function toggleBlock(int $postId): void
    {
        $post = CommunityPost::whereKey($postId)->where('community_id', $this->community->id)->firstOrFail();
        $viewer = Auth::user();

        abort_if($viewer->id === $post->user_id, 403);

        if ($viewer->hasBlocked($post->user)) {
            $viewer->unblock($post->user);
        } else {
            $viewer->block($post->user);
        }

        unset($this->postsWindow, $this->hasMore);
    }

    public function startReport(int $postId): void
    {
        $this->reportingPostId = $postId;
        $this->reportReason = '';
    }

    public function cancelReport(): void
    {
        $this->reportingPostId = null;
        $this->reportReason = '';
    }

    public function submitReport(): void
    {
        $this->validate(['reportReason' => ['required', 'string', 'max:1000']]);

        $post = CommunityPost::whereKey($this->reportingPostId)->where('community_id', $this->community->id)->firstOrFail();

        CommunityReport::file(Auth::user(), $post, $this->reportReason, $this->community);

        $this->reportingPostId = null;
        $this->reportReason = '';

        Flux::toast(variant: 'success', text: __('Report submitted to the community and platform admins.'));
    }

    #[Computed]
    public function postsWindow()
    {
        $viewer = Auth::user();

        // A community feed is passive consumption — scrolling past whoever
        // posts, not a deliberate visit to one person — so muted and
        // blocked authors are filtered out here entirely, on top of
        // whatever this viewer has individually hidden. None of this
        // applies on a profile's own Wall, since visiting it is deliberate.
        $mutedOrBlockedIds = $viewer->muting()->pluck('users.id')
            ->merge($viewer->blocking()->pluck('users.id'))
            ->merge($viewer->blockedBy()->pluck('users.id'));

        // Eager-loaded here so the nested reactions/comments/bookmark
        // components don't each fire their own count/exists query per
        // post — see HasReactions/HasComments/HasBookmarks.
        return $this->community->posts()
            ->whereNotIn('user_id', $mutedOrBlockedIds)
            ->whereDoesntHave('hides', fn ($q) => $q->where('user_id', $viewer->id))
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

<div class="space-y-4" wire:key="community-posts-{{ $community->id }}">
    @forelse ($posts as $post)
        @php $isMine = $post->user_id === Auth::id(); @endphp

        <div id="post-{{ $post->id }}" class="surface-card p-4 target:ring-2 target:ring-score-500" wire:key="community-post-{{ $post->id }}">
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

                @include('partials.post-options-menu', [
                    'post' => $post,
                    'isMine' => $isMine,
                    'canDelete' => $isMine || $community->canModerate(Auth::user()),
                    'shareUrl' => route('communities.show', $community).'#post-'.$post->id,
                    'editDispatchEvent' => 'edit-community-post',
                ])
            </div>

            @if ($post->body)
                <p class="mt-3 whitespace-pre-line text-stone-700 dark:text-stone-300">{!! \App\Support\RichText::render($post->body, $post->hashtags, $post->mentions) !!}</p>
            @endif

            @include('partials.media-grid', ['media' => $post->media])

            <div class="mt-3 flex items-center justify-between border-t border-stone-200 pt-2 dark:border-stone-800">
                <div class="flex items-center gap-1">
                    <livewire:pages::shared.emoji-reactions :reactable="$post" :key="'community-post-emoji-'.$post->id" />
                    <livewire:pages::shared.comments :commentable="$post" :key="'community-post-comments-'.$post->id" />
                </div>
                <div class="flex items-center gap-1">
                    <livewire:pages::shared.bookmark :bookmarkable="$post" :key="'community-post-bookmark-'.$post->id" />
                    <livewire:pages::shared.reactions :reactable="$post" :key="'community-post-reactions-'.$post->id" />
                </div>
            </div>

            @if ($reportingPostId === $post->id)
                <div class="mt-3 rounded-lg bg-white border border-stone-200 p-3 dark:bg-stone-900 dark:border-stone-800">
                    <flux:textarea wire:model="reportReason" :label="__('Why are you reporting this?')" rows="2" />
                    <div class="mt-2 flex justify-end gap-2">
                        <flux:button size="sm" variant="ghost" wire:click="cancelReport">{{ __('Cancel') }}</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="submitReport">{{ __('Submit report') }}</flux:button>
                    </div>
                    @error('reportReason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>
    @empty
        <div class="rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
            <flux:text>{{ __('No posts yet.') }}</flux:text>
        </div>
    @endforelse

    @if ($this->hasMore)
        <div wire:intersect="loadMore" wire:key="community-posts-load-more" class="flex justify-center py-4">
            <flux:icon.loading class="size-5 text-stone-400" />
        </div>
    @endif
</div>
