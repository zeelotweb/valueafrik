<?php

use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public function approve(int $communityId, int $userId): void
    {
        $community = Community::findOrFail($communityId);

        abort_unless($community->canModerate(Auth::user()), 403);

        $community->members()->updateExistingPivot($userId, ['status' => 'active']);

        User::find($userId)?->awardBridgeScore('community_joined', $community);
    }

    public function reject(int $communityId, int $userId): void
    {
        $community = Community::findOrFail($communityId);

        abort_unless($community->canModerate(Auth::user()), 403);

        $community->members()->detach($userId);
    }

    public function with(): array
    {
        $communities = Auth::user()->communities()
            ->wherePivot('status', 'active')
            ->wherePivotIn('role', ['owner', 'monitor'])
            ->with(['members' => fn ($query) => $query->wherePivot('status', 'pending')])
            ->get()
            ->filter(fn ($community) => $community->members->isNotEmpty());

        return ['communities' => $communities];
    }
}; ?>

<div class="space-y-4" wire:key="dashboard-pending-requests">
    @forelse ($communities as $community)
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="pending-community-{{ $community->id }}">
            <flux:link :href="route('communities.show', $community)" wire:navigate class="font-medium">
                {{ $community->name }}
            </flux:link>

            <div class="mt-3 space-y-2">
                @foreach ($community->members as $requester)
                    <div class="flex items-center justify-between" wire:key="pending-{{ $community->id }}-{{ $requester->id }}">
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $requester->name }}</span>
                        <div class="flex gap-2">
                            <flux:button size="sm" variant="primary" class="!bg-cyan-600 hover:!bg-cyan-500" wire:click="approve({{ $community->id }}, {{ $requester->id }})">
                                {{ __('Approve') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="reject({{ $community->id }}, {{ $requester->id }})">
                                {{ __('Reject') }}
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-700">
            <flux:text>{{ __("You're all caught up.") }}</flux:text>
        </div>
    @endforelse
</div>
