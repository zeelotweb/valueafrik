<?php

use App\Models\Community;
use App\Notifications\CommunityJoinRequested;
use App\Support\SafeNotifier;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Community $community;

    #[Computed]
    public function membership(): ?object
    {
        return $this->community->membershipFor(Auth::user());
    }

    #[Computed]
    public function joinable(): bool
    {
        return $this->community->isJoinableBy(Auth::user());
    }

    public function join(): void
    {
        $user = Auth::user();

        abort_unless($this->community->isJoinableBy($user), 422);
        abort_if($this->community->isFull(), 422);

        $status = $this->community->visibility === Community::VISIBILITY_PRIVATE ? 'pending' : 'active';

        $this->community->members()->attach($user->id, ['role' => 'member', 'status' => $status]);

        if ($status === 'active') {
            $user->awardBridgeScore('community_joined', $this->community);
        } else {
            $moderators = $this->community->activeMembers()->wherePivotIn('role', ['owner', 'monitor'])->get();
            SafeNotifier::send($moderators, new CommunityJoinRequested($this->community, $user));
        }

        unset($this->membership, $this->joinable);
        $this->dispatch('community-membership-changed');
    }

    public function leave(): void
    {
        $this->community->members()->detach(Auth::id());

        unset($this->membership, $this->joinable);
        $this->dispatch('community-membership-changed');
    }
}; ?>

<div>
    @unless (Auth::id() === $community->owner_id)
        @if ($this->membership !== null)
            @if ($this->membership->status === 'pending')
                <flux:button size="sm" variant="ghost" disabled data-test="pending-request">{{ __('Request pending') }}</flux:button>
            @else
                <flux:button wire:click="leave" size="sm" variant="ghost" wire:loading.attr="disabled">{{ __('Leave') }}</flux:button>
            @endif
        @elseif ($community->isFull())
            <flux:button size="sm" variant="ghost" disabled>{{ __('Community full') }}</flux:button>
        @elseif ($this->joinable)
            <flux:button wire:click="join" size="sm" variant="primary" class="!bg-cyan-600 hover:!bg-cyan-500" wire:loading.attr="disabled">
                {{ __('Join') }}
            </flux:button>
        @else
            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Only followers of the owner can join') }}</flux:text>
        @endif
    @endunless
</div>
