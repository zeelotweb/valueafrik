<?php
$user = auth()->user();
$bridgeScore = $user->bridgeScore();
$badge = \App\Models\User::badgeForScore($bridgeScore);
$rootsIncomplete = ! $user->profile?->bio || $user->languages->isEmpty() || $user->heritages->isEmpty();
?>
<x-layouts::app :title="__('Dashboard (Lab)')">
    <div class="mx-auto w-full max-w-6xl">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">
                    {{ __('Welcome back, :name', ['name' => Str::before($user->name, ' ')]) }}
                </h1>
                <flux:subheading>{{ __("Here's what's going on across valueAFRIK.") }}</flux:subheading>
            </div>

            <span class="rounded-full border border-dashed border-stone-300 px-3 py-1 text-xs font-medium text-stone-500 dark:border-stone-700 dark:text-stone-400">
                {{ __('Experimental layout — not the real dashboard') }}
            </span>
        </div>

        {{-- Composer-first: the quick actions fold into what they produce,
             instead of sitting in their own row above it. --}}
        <div class="surface-card mt-6 p-4">
            <div class="flex items-center gap-3">
                <div class="size-9 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                    @if ($user->profile?->avatarUrl())
                        <img src="{{ $user->profile->avatarUrl() }}" class="size-full object-cover">
                    @else
                        <div class="flex size-full items-center justify-center text-stone-500">
                            <flux:icon.user class="size-4" />
                        </div>
                    @endif
                </div>

                <flux:modal.trigger name="wall-composer">
                    <button type="button" class="flex-1 rounded-full border border-stone-200 px-4 py-2 text-start text-sm text-stone-400 hover:border-stone-300 hover:bg-stone-50 dark:border-stone-700 dark:hover:border-stone-600 dark:hover:bg-stone-800">
                        {{ __("Share what's on your mind…") }}
                    </button>
                </flux:modal.trigger>
            </div>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap gap-1.5">
                    <flux:modal.trigger name="bridge-post-composer">
                        <flux:button size="sm" variant="ghost" icon="arrows-right-left" class="!bg-score-100 !text-score-700 hover:!bg-score-100/70 dark:!bg-score-950 dark:!text-score-400 dark:hover:!bg-score-950/70">
                            {{ __('Bridge Post') }}
                        </flux:button>
                    </flux:modal.trigger>

                    <livewire:pages::dashboard.start-stream :key="'lab-start-stream-'.$user->id" class="!bg-live-50 !text-live-700 hover:!bg-live-100 dark:!bg-live-950 dark:!text-live-400 dark:hover:!bg-live-950/70" />

                    <a href="{{ route('communities.create') }}" wire:navigate>
                        <flux:button size="sm" variant="ghost" icon="plus" class="!bg-communities-50 !text-communities-700 hover:!bg-communities-100 dark:!bg-communities-950 dark:!text-communities-400 dark:hover:!bg-communities-950/70">
                            {{ __('Community') }}
                        </flux:button>
                    </a>
                </div>

                {{-- Score is ambient here — a small pill, not a stat card
                     competing with content for above-the-fold space. --}}
                <a href="{{ route('profile.show', $user) }}" wire:navigate class="flex items-center gap-1.5 rounded-full bg-score-50 px-3 py-1.5 text-sm font-medium text-score-700 hover:bg-score-100 dark:bg-score-950 dark:text-score-400">
                    <flux:icon.sparkles class="size-3.5" />
                    <span class="font-display">{{ $bridgeScore }}</span>
                    <span class="text-xs text-score-600 dark:text-score-500">{{ $badge['name'] ?? __('Just getting started') }}</span>
                </a>
            </div>
        </div>

        <livewire:pages::profile.wall-composer :key="'lab-wall-composer-'.$user->id" />
        <livewire:pages::profile.bridge-post-composer :key="'lab-bridge-post-composer-'.$user->id" />

        @if ($rootsIncomplete)
            <div class="mt-4 flex flex-col gap-2 rounded-lg border border-dashed border-stone-300 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-stone-700">
                <flux:text>{{ __("You haven't finished your Roots yet — it's how people find common ground with you.") }}</flux:text>
                <flux:link :href="route('roots.edit')" wire:navigate class="shrink-0">{{ __('Finish it') }}</flux:link>
            </div>
        @endif

        <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_300px]">
            {{-- Main column: the feed is the page, not a widget on the page. --}}
            <div class="min-w-0 space-y-8">
                <div>
                    <flux:heading size="lg" class="font-display">{{ __('From people you follow') }}</flux:heading>
                    <div class="mt-3">
                        <livewire:pages::dashboard.following-activity :key="'lab-following-activity-'.$user->id" />
                    </div>
                </div>

                <div>
                    <flux:heading size="lg" class="font-display">{{ __('Fresh Bridge Posts') }}</flux:heading>
                    <flux:subheading>{{ __('Real exchange happening across the platform right now.') }}</flux:subheading>
                    <div class="mt-3">
                        <livewire:pages::dashboard.activity :key="'lab-activity-'.$user->id" />
                    </div>
                </div>
            </div>

            {{-- Side rail: sticky on desktop, stacks below the feed on
                 mobile — discovery and attention items sit alongside the
                 content instead of stacking below it, competing for the
                 same scroll. --}}
            <div class="min-w-0 space-y-6 lg:sticky lg:top-6 lg:self-start">
                <div class="surface-card p-4">
                    <flux:heading size="sm" class="font-display">{{ __('Needs your attention') }}</flux:heading>

                    <div class="mt-3">
                        <flux:subheading>{{ __('Bridge Post invites') }}</flux:subheading>
                        <div class="mt-2">
                            <livewire:pages::dashboard.bridge-post-invites />
                        </div>
                    </div>

                    <div class="mt-4">
                        <flux:subheading>{{ __('Community requests') }}</flux:subheading>
                        <div class="mt-2">
                            <livewire:pages::dashboard.pending-requests />
                        </div>
                    </div>

                    <div class="mt-4">
                        <flux:subheading>{{ __('Live right now') }}</flux:subheading>
                        <div class="mt-2">
                            <livewire:pages::dashboard.live-now :key="'lab-live-now-'.$user->id" />
                        </div>
                    </div>
                </div>

                <div class="surface-card p-4">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm" class="font-display">{{ __('Your communities') }}</flux:heading>
                        <a href="{{ route('communities.create') }}" wire:navigate class="text-xs font-medium text-communities-700 hover:text-communities-600 dark:text-communities-400">
                            {{ __('New') }}
                        </a>
                    </div>
                    <div class="mt-3">
                        <livewire:pages::dashboard.communities-widget :user="$user" :key="'lab-communities-'.$user->id" />
                    </div>
                </div>

                <div class="surface-card p-4">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm" class="font-display">{{ __('People to discover') }}</flux:heading>
                        <a href="{{ route('discover.index') }}" wire:navigate class="text-xs font-medium text-cyan-700 hover:text-cyan-600 dark:text-cyan-400">
                            {{ __('See all') }}
                        </a>
                    </div>
                    <div class="mt-3">
                        <livewire:pages::dashboard.people-widget :key="'lab-people-'.$user->id" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
