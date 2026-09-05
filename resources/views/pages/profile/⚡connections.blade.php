<?php

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Connections')] class extends Component {
    public User $user;

    #[Url(as: 'tab')]
    public string $tab = 'followers';

    public int $perPage = 20;

    public function mount(User $user): void
    {
        $this->user = $user;

        if (! in_array($this->tab, ['followers', 'following'], true)) {
            $this->tab = 'followers';
        }
    }

    public function switchTab(string $tab): void
    {
        $this->tab = in_array($tab, ['followers', 'following'], true) ? $tab : 'followers';
        $this->perPage = 20;
        unset($this->people);
    }

    public function loadMore(): void
    {
        if ($this->people['hasMore']) {
            $this->perPage += 20;
            unset($this->people);
        }
    }

    #[On('follow-toggled')]
    public function refresh(): void
    {
        unset($this->people, $this->followersCount, $this->followingCount);
    }

    #[Computed]
    public function followersCount(): int
    {
        return $this->user->followers()->count();
    }

    #[Computed]
    public function followingCount(): int
    {
        return $this->user->following()->count();
    }

    #[Computed]
    public function people(): array
    {
        $relation = $this->tab === 'following' ? $this->user->following() : $this->user->followers();

        $total = (clone $relation)->count();

        $list = $relation->with('profile')->orderByPivotDesc('created_at')->take($this->perPage)->get();

        return [
            'items' => $list,
            'total' => $total,
            'hasMore' => $list->count() < $total,
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-2xl">
    <flux:heading size="xl">{{ $user->name }}</flux:heading>
    <flux:subheading>{{ __('Followers and following') }}</flux:subheading>

    <div class="mt-6 flex border-b border-stone-200 dark:border-stone-800">
        <button
            type="button"
            wire:click="switchTab('followers')"
            class="flex-1 border-b-2 px-4 py-2.5 text-sm font-medium transition {{ $tab === 'followers' ? 'border-cyan-600 text-cyan-600 dark:border-cyan-400 dark:text-cyan-400' : 'border-transparent text-stone-500 hover:text-stone-700 dark:text-stone-400 dark:hover:text-stone-200' }}"
        >
            {{ trans_choice('1 follower|:count followers', $this->followersCount) }}
        </button>
        <button
            type="button"
            wire:click="switchTab('following')"
            class="flex-1 border-b-2 px-4 py-2.5 text-sm font-medium transition {{ $tab === 'following' ? 'border-cyan-600 text-cyan-600 dark:border-cyan-400 dark:text-cyan-400' : 'border-transparent text-stone-500 hover:text-stone-700 dark:text-stone-400 dark:hover:text-stone-200' }}"
        >
            {{ __('Following') }} ({{ $this->followingCount }})
        </button>
    </div>

    <div class="mt-4 space-y-2" wire:key="connections-list-{{ $tab }}">
        @forelse ($this->people['items'] as $person)
            <div class="flex min-w-0 items-center gap-3 rounded-xl bg-white border border-stone-200 p-4 dark:bg-stone-900 dark:border-stone-800" wire:key="connection-{{ $tab }}-{{ $person->id }}">
                <a href="{{ route('profile.show', $person) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3">
                    <div class="size-12 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                        @if ($person->profile?->avatarUrl())
                            <img src="{{ $person->profile->avatarUrl() }}" class="size-full object-cover">
                        @else
                            <div class="flex size-full items-center justify-center text-stone-500">
                                <flux:icon.user class="size-6" />
                            </div>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium text-stone-900 dark:text-white">{{ $person->name }}</div>
                        <p class="mt-0.5 truncate text-sm text-stone-500 dark:text-stone-400">{{ $person->profile?->bio ?: __('New here.') }}</p>
                    </div>
                </a>

                @if ($person->id !== auth()->id())
                    <livewire:pages::profile.follow-button :user="$person" :key="'connections-follow-'.$tab.'-'.$person->id" :icon-only="true" />
                @endif
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
                <flux:text>
                    {{ $tab === 'followers' ? __('No followers yet.') : __('Not following anyone yet.') }}
                </flux:text>
            </div>
        @endforelse

        @if ($this->people['hasMore'])
            <div wire:intersect="loadMore" class="flex justify-center py-4">
                <flux:icon.loading class="size-5 text-stone-400" />
            </div>
        @endif
    </div>
</div>
