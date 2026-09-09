<?php

use App\Models\BridgePost;
use Livewire\Component;

new class extends Component {
    public function with(): array
    {
        return [
            'bridgePosts' => BridgePost::query()
                ->where('status', BridgePost::STATUS_ACTIVE)
                ->whereNotNull('initiator_body')
                ->whereNotNull('partner_body')
                ->with(['initiator.profile', 'partner.profile'])
                ->latest('updated_at')
                ->limit(3)
                ->get(),
        ];
    }
}; ?>

<div class="space-y-2" wire:key="dashboard-activity" wire:poll.60s>
    @forelse ($bridgePosts as $post)
        {{-- Each person's avatar/name links to their own profile — not one
             shared link to just the initiator, which used to send you to the
             wrong person if you clicked the partner's side. --}}
        <div
            wire:key="activity-{{ $post->id }}"
            class="flex items-center gap-3 rounded-xl border border-cyan-200 p-3 dark:border-cyan-900"
        >
            <div class="flex -space-x-2">
                @foreach ([$post->initiator, $post->partner] as $person)
                    <a href="{{ route('profile.show', $person) }}" wire:navigate class="size-8 shrink-0 overflow-hidden rounded-full border-2 border-white bg-stone-200 dark:border-stone-900 dark:bg-stone-700">
                        @if ($person->profile?->avatarUrl())
                            <img src="{{ $person->profile->avatarUrl() }}" class="size-full object-cover">
                        @else
                            <div class="flex size-full items-center justify-center text-stone-500">
                                <flux:icon.user class="size-4" />
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-stone-900 dark:text-white">
                    <a href="{{ route('profile.show', $post->initiator) }}" wire:navigate class="hover:underline">{{ $post->initiator->name }}</a>
                    &amp;
                    <a href="{{ route('profile.show', $post->partner) }}" wire:navigate class="hover:underline">{{ $post->partner->name }}</a>
                </p>
                <p class="truncate text-xs text-cyan-700 dark:text-cyan-400">{{ $post->theme }}</p>
            </div>

            <flux:icon.arrows-right-left class="size-4 shrink-0 text-cyan-600 dark:text-cyan-400" />
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-stone-300 p-4 text-center dark:border-stone-700">
            <flux:text class="text-sm">{{ __('No Bridge Posts to show yet — start one from your Wall.') }}</flux:text>
        </div>
    @endforelse
</div>
