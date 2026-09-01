<?php

use App\Models\User;
use App\Notifications\NewFollower;
use App\Support\SafeNotifier;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public User $user;
    public bool $overlay = false;
    public bool $iconOnly = false;

    #[Computed]
    public function isFollowing(): bool
    {
        return Auth::user()->isFollowing($this->user);
    }

    #[On('follow-toggled')]
    public function refresh(): void
    {
        unset($this->isFollowing);
    }

    public function toggle(): void
    {
        $viewer = Auth::user();

        abort_if($viewer->id === $this->user->id, 403);

        if ($this->isFollowing) {
            $viewer->following()->detach($this->user->id);

            Flux::toast(text: __('You unfollowed :name.', ['name' => $this->user->name]));
        } else {
            $viewer->following()->attach($this->user->id);

            $viewer->awardBridgeScore('follow', $this->user);

            if ($viewer->isCrossHeritageWith($this->user)) {
                $viewer->awardBridgeScore('follow_cross_heritage_bonus', $this->user);
            }

            $this->user->awardBridgeScore('followed_by_someone', $viewer);

            SafeNotifier::send($this->user, new NewFollower($viewer));

            Flux::toast(variant: 'success', text: __('You are now following :name.', ['name' => $this->user->name]));
        }

        unset($this->isFollowing);

        $this->dispatch('follow-toggled');
    }
}; ?>

<flux:button
    wire:click="toggle"
    wire:loading.attr="disabled"
    size="sm"
    variant="{{ $this->isFollowing ? 'ghost' : 'primary' }}"
    icon="{{ $this->isFollowing ? 'check' : 'user-plus' }}"
    class="{{ $this->isFollowing ? ($overlay ? '!bg-white/90 !text-stone-900 shadow-sm backdrop-blur hover:!bg-white dark:!bg-stone-900/80 dark:!text-white dark:hover:!bg-stone-900' : '') : '!bg-cyan-600 hover:!bg-cyan-500' }}"
    :tooltip="$iconOnly ? ($this->isFollowing ? __('Following') : __('Follow')) : null"
    aria-label="{{ $this->isFollowing ? __('Following') : __('Follow') }}"
    data-test="follow-button"
>
    @unless ($iconOnly)
        <span class="hidden sm:inline">{{ $this->isFollowing ? __('Following') : __('Follow') }}</span>
    @endunless
</flux:button>
