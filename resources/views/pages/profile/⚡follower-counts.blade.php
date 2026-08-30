<?php

use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public User $user;

    public int $followingCount;
    public int $followersCount;

    public function mount(): void
    {
        $this->followingCount = $this->user->following()->count();
        $this->followersCount = $this->user->followers()->count();
    }

    #[On('follow-toggled')]
    public function refreshCounts(): void
    {
        $this->followersCount = $this->user->followers()->count();
    }
}; ?>

<div class="mt-3 flex items-center gap-4 text-sm">
    <span>
        <span class="font-semibold text-zinc-900 dark:text-white">{{ $followingCount }}</span>
        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Following') }}</span>
    </span>
    <span>
        <span class="font-semibold text-zinc-900 dark:text-white">{{ $followersCount }}</span>
        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Followers') }}</span>
    </span>
</div>
