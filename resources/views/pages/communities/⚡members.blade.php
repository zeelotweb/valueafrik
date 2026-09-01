<?php

use App\Models\Community;
use App\Models\User;
use App\Notifications\CommunityJoinApproved;
use App\Notifications\PromotedToMonitor;
use App\Support\SafeNotifier;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public Community $community;

    #[On('community-membership-changed')]
    public function refresh(): void
    {
        //
    }

    public function approve(int $userId): void
    {
        abort_unless($this->community->canModerate(Auth::user()), 403);

        $this->community->members()->updateExistingPivot($userId, ['status' => 'active']);

        $requester = User::find($userId);
        $requester?->awardBridgeScore('community_joined', $this->community);
        if ($requester) {
            SafeNotifier::send($requester, new CommunityJoinApproved($this->community));
        }

        $this->dispatch('community-membership-changed');
    }

    public function reject(int $userId): void
    {
        abort_unless($this->community->canModerate(Auth::user()), 403);

        $this->community->members()->detach($userId);

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

        $this->dispatch('community-membership-changed');
    }

    public function demote(int $userId): void
    {
        abort_unless(Auth::id() === $this->community->owner_id, 403);

        $this->community->members()->updateExistingPivot($userId, ['role' => 'member']);

        $this->dispatch('community-membership-changed');
    }

    public function dismiss(int $userId): void
    {
        abort_unless($this->community->canModerate(Auth::user()), 403);
        abort_if($userId === $this->community->owner_id, 403);

        $this->community->members()->detach($userId);

        $this->dispatch('community-membership-changed');
    }

    public function with(): array
    {
        return [
            'pendingRequests' => $this->community->members()->wherePivot('status', 'pending')->get(),
            'activeMembers' => $this->community->activeMembers()->with('profile')->get(),
        ];
    }
}; ?>

<div>
    @if ($community->canModerate(Auth::user()))
    <div class="mt-10 border-t border-zinc-200 pt-8 dark:border-zinc-700">
        <flux:heading size="lg">{{ __('Manage community') }}</flux:heading>

        @if ($community->visibility === 'private' && $pendingRequests->isNotEmpty())
            <div class="mt-4">
                <flux:subheading>{{ __('Pending join requests') }}</flux:subheading>
                <div class="mt-2 space-y-2">
                    @foreach ($pendingRequests as $requester)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $requester->name }}</span>
                            <div class="flex gap-2">
                                <flux:button size="sm" variant="primary" class="!bg-cyan-600 hover:!bg-cyan-500" wire:click="approve({{ $requester->id }})">
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
                <span class="text-zinc-400">({{ __(':count monitor slots used', ['count' => $community->monitorCount().'/'.$community->monitorSlotLimit()]) }})</span>
            </flux:subheading>

            <div class="mt-2 space-y-2">
                @foreach ($activeMembers as $member)
                    @if ($member->id !== $community->owner_id)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $member->name }}</span>
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
        </div>
    </div>
    @endif
</div>
