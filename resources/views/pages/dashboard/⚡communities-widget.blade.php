<?php

use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public User $user;

    #[On('community-membership-changed')]
    public function refresh(): void
    {
        //
    }

    public function with(): array
    {
        $joined = $this->user->communities()
            ->wherePivot('status', 'active')
            ->withCount('activeMembers')
            ->get();

        $suggested = collect();

        if ($joined->isEmpty()) {
            $suggested = Community::query()
                ->where('visibility', Community::VISIBILITY_PUBLIC)
                ->withCount('activeMembers')
                ->get()
                ->sortByDesc('active_members_count')
                ->filter(fn ($community) => $community->active_members_count > 0)
                ->take(3)
                ->values();
        }

        return [
            'joined' => $joined,
            'suggested' => $suggested,
        ];
    }
}; ?>

<div class="space-y-2" wire:key="dashboard-communities-{{ $user->id }}">
    @forelse ($joined as $community)
        <a
            href="{{ route('communities.show', $community) }}"
            wire:navigate
            wire:key="joined-{{ $community->id }}"
            class="flex items-center gap-3 rounded-xl bg-white border border-stone-200 p-3 hover:bg-stone-50 dark:bg-stone-900 dark:border-stone-800 dark:hover:bg-stone-800"
        >
            <div class="size-11 shrink-0 overflow-hidden rounded-xl bg-stone-200 dark:bg-stone-700">
                @if ($community->avatarUrl())
                    <img src="{{ $community->avatarUrl() }}" class="size-full object-cover">
                @else
                    <div class="flex size-full items-center justify-center text-stone-500">
                        <flux:icon.user-group class="size-5" />
                    </div>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="truncate font-medium text-stone-900 dark:text-white">{{ $community->name }}</span>
                    @if ($community->pivot->role !== 'member')
                        <flux:badge size="sm" color="cyan">{{ ucfirst($community->pivot->role) }}</flux:badge>
                    @endif
                </div>
                <p class="mt-0.5 truncate text-sm text-stone-500 dark:text-stone-400">
                    {{ trans_choice('1 member|:count members', $community->active_members_count) }}
                </p>
            </div>
        </a>
    @empty
        <flux:text class="text-sm text-stone-500 dark:text-stone-400">
            {{ __("You haven't joined a community yet — here's what's active right now.") }}
        </flux:text>

        @forelse ($suggested as $community)
            <div class="flex items-center gap-3 rounded-xl bg-white border border-stone-200 p-3 dark:bg-stone-900 dark:border-stone-800" wire:key="suggested-{{ $community->id }}">
                <a href="{{ route('communities.show', $community) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3">
                    <div class="size-11 shrink-0 overflow-hidden rounded-xl bg-stone-200 dark:bg-stone-700">
                        @if ($community->avatarUrl())
                            <img src="{{ $community->avatarUrl() }}" class="size-full object-cover">
                        @else
                            <div class="flex size-full items-center justify-center text-stone-500">
                                <flux:icon.user-group class="size-5" />
                            </div>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <span class="truncate font-medium text-stone-900 dark:text-white">{{ $community->name }}</span>
                        <p class="mt-0.5 truncate text-sm text-stone-500 dark:text-stone-400">
                            {{ trans_choice('1 member|:count members', $community->active_members_count) }}
                        </p>
                    </div>
                </a>

                <livewire:pages::communities.join-button :community="$community" :key="'dashboard-join-'.$community->id" />
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-700">
                <flux:text>{{ __('No communities yet — be the first to start one.') }}</flux:text>
            </div>
        @endforelse
    @endforelse
</div>
