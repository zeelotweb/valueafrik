<?php

use App\Models\GameSession;

$hasAyoInProgress = GameSession::activeOfType(auth()->id(), GameSession::TYPE_AYO)->exists();

?>
<x-layouts::app :title="__('Games')">
    <div class="mx-auto w-full max-w-3xl">
        <flux:heading size="xl">{{ __('Games') }}</flux:heading>
        <flux:subheading>{{ __('Board games from across the diaspora — starting with one from Yorùbá culture.') }}</flux:subheading>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <a href="{{ route('games.ayo.play') }}" wire:navigate class="surface-card group flex flex-col p-5 transition hover:border-stone-300 dark:hover:border-stone-700">
                <div class="flex items-start justify-between gap-3">
                    <span class="flex size-11 items-center justify-center rounded-lg bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300">
                        <flux:icon.squares-2x2 class="size-6" />
                    </span>
                    <span class="rounded-full border border-stone-200 px-2.5 py-1 text-xs font-medium text-stone-500 dark:border-stone-800 dark:text-stone-400">
                        {{ __('2 players, 1 device') }}
                    </span>
                </div>

                <h2 class="mt-4 font-semibold text-stone-900 dark:text-white">{{ __('Ayo') }}</h2>
                <p class="mt-1.5 text-sm text-stone-500 dark:text-stone-400">
                    {{ __('A Yorùbá seed-sowing game of capture and strategy — pass the device and play sitting side by side.') }}
                </p>

                <span class="btn-flat-primary mt-4 inline-flex w-fit items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium">
                    {{ $hasAyoInProgress ? __('Continue game') : __('Play') }}
                    <flux:icon.arrow-right class="size-3.5" />
                </span>
            </a>

            <div class="surface-card flex flex-col items-center justify-center gap-2 p-5 text-center text-stone-400 dark:text-stone-500">
                <flux:icon.plus class="size-6" />
                <p class="text-sm font-medium">{{ __('More games are on the way') }}</p>
                <x-coming-soon-badge />
            </div>
        </div>
    </div>
</x-layouts::app>
