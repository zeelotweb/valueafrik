<?php

use App\Models\Community;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Communities')] class extends Component {
    public function with(): array
    {
        $userId = Auth::id();

        $communities = Community::query()
            ->withCount('activeMembers')
            ->where(function ($query) use ($userId) {
                $query->where('visibility', '!=', Community::VISIBILITY_PRIVATE)
                    ->orWhereHas('members', fn ($q) => $q->whereKey($userId)->where('community_user.status', 'active'));
            })
            ->latest()
            ->get();

        return ['communities' => $communities];
    }
}; ?>

<div class="mx-auto w-full max-w-3xl">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Communities') }}</flux:heading>

        <a href="{{ route('communities.create') }}" wire:navigate>
            <flux:button variant="primary" class="!bg-cyan-600 hover:!bg-cyan-500">{{ __('Create community') }}</flux:button>
        </a>
    </div>

    <div class="mt-6 grid gap-3 sm:grid-cols-2">
        @forelse ($communities as $community)
            <a
                href="{{ route('communities.show', $community) }}"
                wire:navigate
                class="flex items-start gap-3 rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
            >
                <div class="size-12 shrink-0 overflow-hidden rounded-xl bg-zinc-200 dark:bg-zinc-700">
                    @if ($community->avatarUrl())
                        <img src="{{ $community->avatarUrl() }}" class="size-full object-cover">
                    @else
                        <div class="flex size-full items-center justify-center text-zinc-500">
                            <flux:icon.user-group class="size-6" />
                        </div>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="truncate font-medium text-zinc-900 dark:text-white">{{ $community->name }}</span>
                        @if ($community->visibility !== 'public')
                            <flux:badge size="sm" color="zinc">
                                {{ $community->visibility === 'private' ? __('Private') : __('Followers only') }}
                            </flux:badge>
                        @endif
                    </div>
                    <p class="mt-0.5 truncate text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $community->description ?: __('No description yet.') }}
                    </p>
                    <p class="mt-1 text-xs text-zinc-400">
                        {{ trans_choice('1 member|:count members', $community->active_members_count) }}
                    </p>
                </div>
            </a>
        @empty
            <div class="col-span-2 rounded-lg border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-700">
                <flux:text>{{ __('No communities yet — be the first to start one.') }}</flux:text>
            </div>
        @endforelse
    </div>
</div>
