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
    <a href="{{ route('profile.connections', ['user' => $user, 'tab' => 'following']) }}" wire:navigate class="hover:underline">
        <span class="font-semibold text-stone-900 dark:text-white">{{ $followingCount }}</span>
        <span class="text-stone-500 dark:text-stone-400">{{ __('Following') }}</span>
    </a>
    <a href="{{ route('profile.connections', ['user' => $user, 'tab' => 'followers']) }}" wire:navigate class="hover:underline">
        <span class="font-semibold text-stone-900 dark:text-white">{{ $followersCount }}</span>
        <span class="text-stone-500 dark:text-stone-400">{{ __('Followers') }}</span>
    </a>
</div>
