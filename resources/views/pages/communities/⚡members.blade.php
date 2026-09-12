<?php

use App\Models\Community;
use App\Models\User;
use App\Notifications\CommunityJoinApproved;
use App\Notifications\PromotedToMonitor;
use App\Support\SafeNotifier;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public Community $community;

    public int $perPage = 20;

    public int $loaded = 20;

    #[On('community-membership-changed')]
    public function refresh(): void
    {
        unset($this->activeMembersWindow, $this->hasMore);
    }

    public function loadMore(): void
    {
        if ($this->hasMore) {
            $this->loaded += $this->perPage;

            unset($this->activeMembersWindow, $this->hasMore);
        }
    }

    public function approve(int $userId): void
    {
        abort_unless($this->community->canModerate(Auth::user()), 403);

        $this->community->members()->updateExistingPivot($userId, ['status' => 'active']);

        $requester = User::find($userId);
        if ($requester && ! $requester->hasEarnedBridgeScoreFor('community_joined', $this->community)) {
            $requester->awardBridgeScore('community_joined', $this->community);
        }
        if ($requester) {
            SafeNotifier::send($requester, new CommunityJoinApproved($this->community));
        }

        Flux::toast(variant: 'success', text: __(':name approved.', ['name' => $requester?->name]));

        $this->dispatch('community-membership-changed');
    }

    public function reject(int $userId): void
    {
        abort_unless($this->community->canModerate(Auth::user()), 403);

        $this->community->members()->detach($userId);

        Flux::toast(text: __('Request declined.'));

        $this->dispatch('community-membership-changed');
    }

    public function promote(int $userId): void
    {
        abort_unless(Auth::id() === $this->community->owner_id, 403);
        abort_unless($this->community->canPromoteMonitor(), 422);

        $this->community->members()->updateExistingPivot($userId, ['role' => 'monitor']);

        $promoted = User::find($userId);
        $promoted?->awardBridgeScore('promoted_to_monitor', $this->community);
        if ($promoted) {
            SafeNotifier::send($promoted, new PromotedToMonitor($this->community));
        }

        Flux::toast(variant: 'success', text: __(':name is now a monitor.', ['name' => $promoted?->name]));

        $this->dispatch('community-membership-changed');
    }

    public function demote(int $userId): void
    {
        abort_unless(Auth::id() === $this->community->owner_id, 403);

        $this->community->members()->updateExistingPivot($userId, ['role' => 'member']);

        Flux::toast(text: __('Monitor role removed.'));

        $this->dispatch('community-membership-changed');
    }

    public function dismiss(int $userId): void
    {
        abort_unless($this->community->canModerate(Auth::user()), 403);
        abort_if($userId === $this->community->owner_id, 403);

        $this->community->members()->detach($userId);

        Flux::toast(text: __('Member removed.'));

        $this->dispatch('community-membership-changed');
    }

    #[Computed]
    public function activeMembersWindow()
    {
        // Owner is filtered out below in the template (never actionable
        // from this list), but kept in the fetch/count so the window math
        // stays simple — one fewer row shown just means loadMore's "more?"
        // check errs slightly early, never late.
        // A deterministic order is required for the window (limit N+1,
        // take N) to page through consistently — without one, SQL doesn't
        // guarantee the same row order across the two queries loadMore()
        // implicitly runs.
        return $this->community->activeMembers()
            ->with('profile')
            ->orderBy('community_user.created_at')
            ->limit($this->loaded + 1)
            ->get();
    }

    #[Computed]
    public function hasMore(): bool
    {
        return $this->activeMembersWindow->count() > $this->loaded;
    }

    public function with(): array
    {
        return [
            'pendingRequests' => $this->community->members()->wherePivot('status', 'pending')->get(),
            'activeMembers' => $this->activeMembersWindow->take($this->loaded),
        ];
    }
}; ?>

<div>
    @if ($community->canModerate(Auth::user()))
    <div class="mt-10 border-t border-stone-200 pt-8 dark:border-stone-800">
        <flux:heading size="lg">{{ __('Manage community') }}</flux:heading>

        @if ($community->visibility === 'private' && $pendingRequests->isNotEmpty())
            <div class="mt-4">
                <flux:subheading>{{ __('Pending join requests') }}</flux:subheading>
                <div class="mt-2 space-y-2">
                    @foreach ($pendingRequests as $requester)
                        <div class="flex items-center justify-between rounded-lg bg-white border border-stone-200 p-3 dark:bg-stone-900 dark:border-stone-800">
                            <span class="text-sm font-medium text-stone-900 dark:text-white">{{ $requester->name }}</span>
                            <div class="flex gap-2">
                                <flux:button size="sm" variant="primary" color="cyan" wire:click="approve({{ $requester->id }})">
                                    {{ __('Approve') }}
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="reject({{ $requester->id }})">
                                    {{ __('Reject') }}
                                </flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-6">
            <flux:subheading>
                {{ __('Members') }}
                <span class="text-stone-400">({{ __(':count monitor slots used', ['count' => $community->monitorCount().'/'.$community->monitorSlotLimit()]) }})</span>
            </flux:subheading>

            <div class="mt-2 space-y-2">
                @foreach ($activeMembers as $member)
                    @if ($member->id !== $community->owner_id)
                        <div class="flex items-center justify-between rounded-lg bg-white border border-stone-200 p-3 dark:bg-stone-900 dark:border-stone-800">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-stone-900 dark:text-white">{{ $member->name }}</span>
                                @if ($member->pivot->role === 'monitor')
                                    <flux:badge size="sm" color="cyan">{{ __('Monitor') }}</flux:badge>
                                @endif
                            </div>

                            @if (Auth::id() === $community->owner_id)
                                <div class="flex gap-2">
                                    @if ($member->pivot->role === 'monitor')
                                        <flux:button size="sm" variant="ghost" wire:click="demote({{ $member->id }})">
                                            {{ __('Remove monitor') }}
                                        </flux:button>
                                    @elseif ($community->canPromoteMonitor())
                                        <flux:button size="sm" variant="ghost" wire:click="promote({{ $member->id }})">
                                            {{ __('Make monitor') }}
                                        </flux:button>
                                    @endif
                                    <flux:button size="sm" variant="danger" wire:click="dismiss({{ $member->id }})" wire:confirm="{{ __('Remove this member from the community?') }}">
                                        {{ __('Remove') }}
                                    </flux:button>
                                </div>
                            @else
                                <flux:button size="sm" variant="danger" wire:click="dismiss({{ $member->id }})" wire:confirm="{{ __('Remove this member from the community?') }}">
                                    {{ __('Remove') }}
                                </flux:button>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>

            @if ($this->hasMore)
                <div wire:intersect="loadMore" wire:key="community-members-load-more" class="flex justify-center py-3">
                    <flux:icon.loading class="size-4 text-stone-400" />
                </div>
            @endif
        </div>
    </div>
    @endif
</div>
