<?php

use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public User $user;

    public function with(): array
    {
        $isOwnProfile = Auth::id() === $this->user->id;

        $communities = $this->user->communities()
            ->wherePivot('status', 'active')
            ->when(! $isOwnProfile, fn ($query) => $query->where('visibility', '!=', Community::VISIBILITY_PRIVATE))
            ->withCount('activeMembers')
            ->get();

        return [
            'communities' => $communities,
            'isOwnProfile' => $isOwnProfile,
        ];
    }
}; ?>

<div class="space-y-2">
    @forelse ($communities as $community)
        <a
            href="{{ route('communities.show', $community) }}"
            wire:navigate
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

                    @if ($community->visibility !== 'public')
                        <flux:badge size="sm" color="zinc">
                            {{ $community->visibility === 'private' ? __('Private') : __('Followers only') }}
                        </flux:badge>
                    @endif
                </div>
                <p class="mt-0.5 truncate text-sm text-stone-500 dark:text-stone-400">
                    {{ trans_choice('1 member|:count members', $community->active_members_count) }}
                </p>
            </div>
        </a>
    @empty
        <div class="rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
            <flux:text>
                @if ($isOwnProfile)
                    {{ __("You haven't joined any communities yet.") }}
                    <flux:link :href="route('communities.index')" wire:navigate>{{ __('Browse communities') }}</flux:link>
                @else
                    {{ __("{$user->name} hasn't joined any public communities yet.") }}
                @endif
            </flux:text>
        </div>
    @endforelse
</div>
