<?php

use App\Models\LiveSession;
use Livewire\Component;

new class extends Component {
    public function with(): array
    {
        return [
            'streams' => LiveSession::query()
                ->where('type', LiveSession::TYPE_STREAM)
                ->where('status', LiveSession::STATUS_LIVE)
                ->with('host.profile')
                ->latest('started_at')
                ->limit(3)
                ->get(),
        ];
    }
}; ?>

<div wire:poll.30s>
    @forelse ($streams as $stream)
        <a
            href="{{ route('live.show', $stream) }}"
            wire:navigate
            wire:key="dashboard-live-{{ $stream->id }}"
            class="flex items-center gap-3 rounded-xl border border-stone-200 p-3 hover:bg-stone-50 dark:border-stone-800 dark:hover:bg-stone-800"
        >
            <div class="relative size-10 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                @if ($stream->host->profile?->avatarUrl())
                    <img src="{{ $stream->host->profile->avatarUrl() }}" class="size-full object-cover">
                @else
                    <div class="flex size-full items-center justify-center text-stone-500">
                        <flux:icon.user class="size-5" />
                    </div>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-stone-900 dark:text-white">
                    {{ $stream->title ?: $stream->host->name }}
                </p>
                <p class="truncate text-xs text-stone-500 dark:text-stone-400">{{ $stream->host->name }}</p>
            </div>

            <flux:badge size="sm" color="cyan">{{ __('Live') }}</flux:badge>
        </a>
    @empty
        <div class="rounded-xl border border-dashed border-stone-300 p-4 text-center dark:border-stone-700">
            <flux:text class="text-sm">{{ __('No one is streaming right now.') }}</flux:text>
            <livewire:pages::dashboard.start-stream :key="'live-now-start-stream'" />
        </div>
    @endforelse
</div>
