<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Messages')] class extends Component {
    public function with(): array
    {
        $userId = Auth::id();

        $conversations = Auth::user()->conversations()
            ->whereHas('messages')
            ->with([
                'latestMessage.media',
                'participants' => fn ($query) => $query->where('users.id', '!=', $userId)->with('profile'),
            ])
            ->get()
            ->sortByDesc(fn ($conversation) => $conversation->latestMessage?->created_at ?? $conversation->created_at)
            ->values();

        return ['conversations' => $conversations];
    }
}; ?>

<div class="mx-auto w-full max-w-2xl">
    <flux:heading size="xl">{{ __('Messages') }}</flux:heading>

    <div class="mt-6 space-y-2">
        @forelse ($conversations as $conversation)
            @php
                $other = $conversation->participants->first();
                $lastRead = $conversation->pivot->last_read_at;
                $last = $conversation->latestMessage;
                $isUnread = $last && $last->user_id !== Auth::id() && (! $lastRead || $lastRead->lt($last->created_at));
                $preview = $last
                    ? ($last->body ?: ($last->media->isNotEmpty() ? __('📷 Photo') : ''))
                    : __('No messages yet');
            @endphp

            <a
                href="{{ route('messages.show', $conversation) }}"
                wire:navigate
                class="flex items-center gap-3 rounded-xl border border-stone-200 p-3 hover:bg-stone-50 dark:border-stone-800 dark:hover:bg-stone-800"
            >
                <div class="size-11 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                    @if ($other?->profile?->avatarUrl())
                        <img src="{{ $other->profile->avatarUrl() }}" class="size-full object-cover">
                    @else
                        <div class="flex size-full items-center justify-center text-stone-500">
                            <flux:icon.user class="size-5" />
                        </div>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate font-medium {{ $isUnread ? 'text-stone-900 dark:text-white' : 'text-stone-700 dark:text-stone-300' }}">
                            {{ $other?->name ?? __('Unknown') }}
                        </span>
                        @if ($last)
                            <span class="shrink-0 text-xs text-stone-500 dark:text-stone-400">
                                {{ $last->created_at->diffForHumans(null, true) }}
                            </span>
                        @endif
                    </div>
                    <p class="truncate text-sm {{ $isUnread ? 'font-medium text-stone-900 dark:text-white' : 'text-stone-500 dark:text-stone-400' }}">
                        {{ $preview }}
                    </p>
                </div>

                @if ($isUnread)
                    <span class="size-2.5 shrink-0 rounded-full bg-cyan-600"></span>
                @endif
            </a>
        @empty
            <div class="rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
                <flux:text>{{ __("You don't have any messages yet.") }}</flux:text>
            </div>
        @endforelse
    </div>
</div>
