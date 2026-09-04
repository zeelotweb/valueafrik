<?php

use App\Models\LiveSession;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Live')] class extends Component {
    public function with(): array
    {
        return [
            'streams' => LiveSession::query()
                ->where('type', LiveSession::TYPE_STREAM)
                ->where('status', LiveSession::STATUS_LIVE)
                ->with('host.profile')
                ->latest('started_at')
                ->get(),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-3xl">
    <flux:heading size="xl">{{ __('Live') }}</flux:heading>
    <flux:subheading>{{ __('Streams happening right now.') }}</flux:subheading>

    <div class="mt-6 grid gap-3 sm:grid-cols-2">
        @forelse ($streams as $stream)
            <a
                href="{{ route('live.show', $stream) }}"
                wire:navigate
                class="flex items-center gap-3 rounded-xl border border-stone-200 p-4 hover:bg-stone-50 dark:border-stone-800 dark:hover:bg-stone-800"
            >
                <div class="size-12 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                    @if ($stream->host->profile?->avatarUrl())
                        <img src="{{ $stream->host->profile->avatarUrl() }}" class="size-full object-cover">
                    @else
                        <div class="flex size-full items-center justify-center text-stone-500">
                            <flux:icon.user class="size-6" />
                        </div>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium text-stone-900 dark:text-white">
                        {{ $stream->title ?: $stream->host->name }}
                    </div>
                    <p class="mt-0.5 truncate text-sm text-stone-500 dark:text-stone-400">{{ $stream->host->name }}</p>
                </div>

                <flux:badge size="sm" color="green">{{ __('Live') }}</flux:badge>
            </a>
        @empty
            <div class="col-span-2 rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
                <flux:text>{{ __('No one is streaming right now.') }}</flux:text>
            </div>
        @endforelse
    </div>
</div>
