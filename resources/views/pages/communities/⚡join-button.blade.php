<?php

use App\Models\Community;
use App\Notifications\CommunityJoinRequested;
use App\Support\SafeNotifier;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Community $community;
    public bool $overlay = false;

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

        try {
            $this->community->members()->attach($user->id, ['role' => 'member', 'status' => $status]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // A concurrent request (double-click, two tabs) already created
            // this membership row — the desired end state is reached
            // either way, so this one just refreshes instead of 500ing.
            unset($this->membership, $this->joinable);

            return;
        }

        if ($status === 'active') {
            // Scoped to this community, not the reason globally — a genuine
            // farming loop otherwise, since leave() has no cooldown and
            // isJoinableBy() goes straight back to true the moment you
            // leave: join, leave, join, leave... would award every time.
            if (! $user->hasEarnedBridgeScoreFor('community_joined', $this->community)) {
                $user->awardBridgeScore('community_joined', $this->community);
            }
        } else {
            $moderators = $this->community->activeMembers()->wherePivotIn('role', ['owner', 'monitor'])->get();
            SafeNotifier::send($moderators, new CommunityJoinRequested($this->community, $user));
        }

        unset($this->membership, $this->joinable);
        $this->dispatch('community-membership-changed');
    }

    /**
     * The "Leave" button is already hidden for the owner in the template
     * below, but that's UI-only — Livewire::test() (and a forged request)
     * reaches this method directly, bypassing it. Without this check the
     * owner could detach themselves from their own community's pivot row
     * while communities.owner_id still points at them: canModerate()/
     * canView() key off the pivot role, not that column, so the community
     * is left with no one who can moderate, approve joins, or promote —
     * permanently, since there's no ownership-transfer feature to recover
     * it. Matches the identical "can't remove the owner" guard dismiss()
     * already enforces for moderator-initiated removal.
     */
    public function leave(): void
    {
        abort_if(Auth::id() === $this->community->owner_id, 403);

        $this->community->members()->detach(Auth::id());

        unset($this->membership, $this->joinable);
        $this->dispatch('community-membership-changed');
    }
}; ?>

<?php $pill = $overlay ? 'btn-overlay-neutral' : ''; ?>
<div>
    @unless (Auth::id() === $community->owner_id)
        @if ($this->membership !== null)
            @if ($this->membership->status === 'pending')
                <flux:button size="sm" variant="ghost" class="{{ $pill }}" disabled data-test="pending-request">{{ __('Request pending') }}</flux:button>
            @else
                <flux:button wire:click="leave" size="sm" variant="ghost" class="{{ $pill }}" wire:loading.attr="disabled">{{ __('Leave') }}</flux:button>
            @endif
        @elseif ($community->isFull())
            <flux:button size="sm" variant="ghost" class="{{ $pill }}" disabled>{{ __('Community full') }}</flux:button>
        @elseif ($this->joinable)
            <flux:button wire:click="join" size="sm" variant="primary" wire:loading.attr="disabled" class="!bg-communities-600 hover:!bg-communities-500">
                {{ __('Join') }}
            </flux:button>
        @else
            <flux:text size="sm" class="{{ $overlay ? 'btn-overlay-neutral rounded-md px-2 py-1' : 'text-stone-500 dark:text-stone-400' }}">{{ __('Only followers of the owner can join') }}</flux:text>
        @endif
    @endunless
</div>
