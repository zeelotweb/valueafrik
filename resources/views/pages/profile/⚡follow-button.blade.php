<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public User $user;

    #[Computed]
    public function isFollowing(): bool
    {
        return Auth::user()->isFollowing($this->user);
    }

    public function toggle(): void
    {
        $viewer = Auth::user();

        abort_if($viewer->id === $this->user->id, 403);

        if ($this->isFollowing) {
            $viewer->following()->detach($this->user->id);
        } else {
            $viewer->following()->attach($this->user->id);

            $viewer->awardBridgeScore('follow', $this->user);

            if ($viewer->isCrossHeritageWith($this->user)) {
                $viewer->awardBridgeScore('follow_cross_heritage_bonus', $this->user);
            }

            $this->user->awardBridgeScore('followed_by_someone', $viewer);
        }

        unset($this->isFollowing);

        $this->dispatch('follow-toggled');
    }
}; ?>

<flux:button
    wire:click="toggle"
    wire:loading.attr="disabled"
    variant="{{ $this->isFollowing ? 'ghost' : 'primary' }}"
    class="{{ $this->isFollowing ? '' : '!bg-cyan-600 hover:!bg-cyan-500' }}"
    data-test="follow-button"
>
    {{ $this->isFollowing ? __('Following') : __('Follow') }}
</flux:button>
