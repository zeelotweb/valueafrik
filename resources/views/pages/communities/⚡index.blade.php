<?php

use App\Models\Community;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Communities')] class extends Component {
    public string $search = '';

    public function with(): array
    {
        $userId = Auth::id();

        $communities = Community::query()
            ->withCount('activeMembers')
            ->where(function ($query) use ($userId) {
                $query->where('visibility', '!=', Community::VISIBILITY_PRIVATE)
                    ->orWhereHas('members', fn ($q) => $q->whereKey($userId)->where('community_user.status', 'active'));
            })
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->latest()
            ->get();

        return ['communities' => $communities];
    }
}; ?>

<div class="mx-auto w-full max-w-3xl">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Communities') }}</flux:heading>

        <a href="{{ route('communities.create') }}" wire:navigate>
            <flux:button variant="primary" color="cyan">{{ __('Create community') }}</flux:button>
        </a>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search communities…') }}" class="mt-4" />

    <div class="mt-6 grid gap-3 sm:grid-cols-2">
        @forelse ($communities as $community)
            <a
                href="{{ route('communities.show', $community) }}"
                wire:navigate
                class="flex min-w-0 items-start gap-3 rounded-xl bg-white border border-stone-200 p-4 hover:bg-stone-50 dark:bg-stone-900 dark:border-stone-800 dark:hover:bg-stone-800"
            >
                <div class="size-12 shrink-0 overflow-hidden rounded-xl bg-stone-200 dark:bg-stone-700">
                    @if ($community->avatarUrl())
                        <img src="{{ $community->avatarUrl() }}" class="size-full object-cover">
                    @else
                        <div class="flex size-full items-center justify-center text-stone-500">
                            <flux:icon.user-group class="size-6" />
                        </div>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="truncate font-medium text-stone-900 dark:text-white">{{ $community->name }}</span>
                        @if ($community->visibility !== 'public')
                            <flux:badge size="sm" color="zinc">
                                {{ $community->visibility === 'private' ? __('Private') : __('Followers only') }}
                            </flux:badge>
                        @endif
                    </div>
                    <p class="mt-0.5 truncate text-sm text-stone-500 dark:text-stone-400">
                        {{ $community->description ?: __('No description yet.') }}
                    </p>
                    <p class="mt-1 text-xs text-stone-400">
                        {{ trans_choice('1 member|:count members', $community->active_members_count) }}
                    </p>
                </div>
            </a>
        @empty
            <div class="col-span-2 rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
                <flux:text>
                    {{ $search !== '' ? __('No communities match ":search".', ['search' => $search]) : __('No communities yet — be the first to start one.') }}
                </flux:text>
            </div>
        @endforelse
    </div>
</div>
