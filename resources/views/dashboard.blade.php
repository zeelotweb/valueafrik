<?php
$user = auth()->user();
$bridgeScore = $user->bridgeScore();
$badge = \App\Models\User::badgeForScore($bridgeScore);
$rootsIncomplete = ! $user->profile?->bio || $user->languages->isEmpty() || $user->heritages->isEmpty();
?>
<x-layouts::app :title="__('Dashboard')">
    <div class="mx-auto w-full max-w-5xl">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">
                    {{ __('Welcome back, :name', ['name' => Str::before($user->name, ' ')]) }}
                </h1>
                <flux:subheading>{{ __("Here's what's going on across valueAFRIK.") }}</flux:subheading>
            </div>
        </div>

        @php
            $quickActionClass = '!bg-stone-200 hover:!bg-stone-300 dark:!bg-stone-800 dark:hover:!bg-stone-700';
            // Bridge Post is the one quick action that shares Bridge Score's
            // warm accent — it's literally how that score gets earned, so the
            // color is doing the same job as it does on the stat card.
            $bridgePostActionClass = '!bg-score-100 !text-score-700 hover:!bg-score-100/70 dark:!bg-score-950 dark:!text-score-400 dark:hover:!bg-score-950/70';
            $streamActionClass = '!bg-live-50 !text-live-700 hover:!bg-live-100 dark:!bg-live-950 dark:!text-live-400 dark:hover:!bg-live-950/70';
            // Same reasoning as Bridge Post/Bridge Score: this button creates
            // the exact thing the Communities stat card counts.
            $communityActionClass = '!bg-communities-50 !text-communities-700 hover:!bg-communities-100 dark:!bg-communities-950 dark:!text-communities-400 dark:hover:!bg-communities-950/70';
        @endphp

        {{-- Quick actions --}}
        <div class="mt-4 flex flex-wrap gap-2">
            <livewire:pages::dashboard.start-stream :key="'start-stream-'.$user->id" class="{{ $streamActionClass }}" />

            <flux:modal.trigger name="wall-composer">
                <flux:button size="sm" variant="ghost" icon="pencil-square" class="{{ $quickActionClass }}">{{ __('Post to Wall') }}</flux:button>
            </flux:modal.trigger>

            <flux:modal.trigger name="bridge-post-composer">
                <flux:button size="sm" variant="ghost" icon="arrows-right-left" class="{{ $bridgePostActionClass }}">{{ __('Start a Bridge Post') }}</flux:button>
            </flux:modal.trigger>

            <a href="{{ route('communities.create') }}" wire:navigate>
                <flux:button size="sm" variant="ghost" icon="plus" class="{{ $communityActionClass }}">{{ __('Create Community') }}</flux:button>
            </a>

            <a href="{{ route('roots.edit') }}" wire:navigate>
                <flux:button size="sm" variant="ghost" icon="identification" class="btn-flat-primary">{{ __('Edit Roots') }}</flux:button>
            </a>
        </div>

        {{-- Mounted here (hidden until triggered) so the quick actions above
             can open them directly, without navigating to the wall first. --}}
        <livewire:pages::profile.wall-composer :key="'dashboard-wall-composer-'.$user->id" />
        <livewire:pages::profile.bridge-post-composer :key="'dashboard-bridge-post-composer-'.$user->id" />

        @if ($rootsIncomplete)
            <div class="mt-4 flex flex-col gap-2 rounded-lg border border-dashed border-stone-300 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-stone-700">
                <flux:text>{{ __("You haven't finished your Roots yet — it's how people find common ground with you.") }}</flux:text>
                <flux:link :href="route('roots.edit')" wire:navigate class="shrink-0">{{ __('Finish it') }}</flux:link>
            </div>
        @endif

        <div class="mt-6 flex flex-wrap gap-4">
            <a
                href="{{ route('profile.show', $user) }}"
                wire:navigate
                class="surface-card w-fit min-w-40 p-5 hover:border-score-500/40 dark:hover:border-score-400/40"
            >
                <div class="flex items-center gap-2 text-score-600 dark:text-score-400">
                    <flux:icon.sparkles class="size-5" />
                    <span class="text-sm font-medium">{{ __('Bridge Score') }}</span>
                </div>
                <p class="font-display mt-2 text-3xl font-semibold text-stone-900 dark:text-white">{{ $bridgeScore }}</p>
                <p class="mt-1 truncate text-sm text-stone-500 dark:text-stone-400">
                    {{ $badge['name'] ?? __('Just getting started') }}
                </p>
            </a>

            <a
                href="{{ route('communities.index') }}"
                wire:navigate
                class="surface-card w-fit min-w-40 p-5 hover:border-communities-600/40 dark:hover:border-communities-500/40"
            >
                <div class="flex items-center gap-2 text-communities-700 dark:text-communities-400">
                    <flux:icon.user-group class="size-5" />
                    <span class="text-sm font-medium">{{ __('Communities') }}</span>
                </div>
                <p class="font-display mt-2 text-3xl font-semibold text-stone-900 dark:text-white">
                    {{ $user->communities()->wherePivot('status', 'active')->count() }}
                </p>
                <p class="mt-1 text-sm text-stone-500 dark:text-stone-400">{{ __('joined') }}</p>
            </a>

            <a
                href="{{ route('messages.index') }}"
                wire:navigate
                class="surface-card w-fit min-w-40 p-5 hover:border-messages-600/40 dark:hover:border-messages-400/40"
            >
                <div class="flex items-center gap-2 text-messages-700 dark:text-messages-400">
                    <flux:icon.chat-bubble-left-right class="size-5" />
                    <span class="text-sm font-medium">{{ __('Messages') }}</span>
                </div>
                <p class="font-display mt-2 text-3xl font-semibold text-stone-900 dark:text-white">{{ $user->unreadConversationsCount() }}</p>
                <p class="mt-1 text-sm text-stone-500 dark:text-stone-400">{{ __('unread') }}</p>
            </a>
        </div>

        <div class="mt-10">
            <flux:heading size="lg" class="font-display">{{ __('From people you follow') }}</flux:heading>
            <flux:subheading>{{ __('Their latest posts, bridges, and communities.') }}</flux:subheading>
            <div class="mt-3">
                <livewire:pages::dashboard.following-activity :key="'dashboard-following-activity-'.$user->id" />
            </div>
        </div>

        <div class="mt-10 grid gap-8 lg:grid-cols-2">
            <div class="min-w-0">
                <flux:heading size="lg" class="font-display">{{ __('Needs your attention') }}</flux:heading>

                <div class="mt-3">
                    <flux:subheading>{{ __('Bridge Post invites') }}</flux:subheading>
                    <div class="mt-2">
                        <livewire:pages::dashboard.bridge-post-invites />
                    </div>
                </div>

                <div class="mt-5">
                    <flux:subheading>{{ __('Community requests') }}</flux:subheading>
                    <div class="mt-2">
                        <livewire:pages::dashboard.pending-requests />
                    </div>
                </div>

                <div class="mt-5">
                    <flux:subheading>{{ __('Live right now') }}</flux:subheading>
                    <div class="mt-2">
                        <livewire:pages::dashboard.live-now :key="'dashboard-live-now-'.$user->id" />
                    </div>
                </div>
            </div>

            <div class="min-w-0">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg" class="font-display">{{ __('Your communities') }}</flux:heading>
                    <a href="{{ route('communities.create') }}" wire:navigate class="text-sm font-medium text-cyan-600 hover:text-cyan-500 dark:text-cyan-400">
                        {{ __('New community') }}
                    </a>
                </div>
                <div class="mt-3">
                    <livewire:pages::dashboard.communities-widget :user="$user" :key="'dashboard-communities-'.$user->id" />
                </div>

                <div class="mt-6 flex items-center justify-between">
                    <flux:heading size="lg" class="font-display">{{ __('People to discover') }}</flux:heading>
                    <a href="{{ route('discover.index') }}" wire:navigate class="text-sm font-medium text-cyan-600 hover:text-cyan-500 dark:text-cyan-400">
                        {{ __('See all') }}
                    </a>
                </div>
                <div class="mt-3">
                    <livewire:pages::dashboard.people-widget :key="'dashboard-people-'.$user->id" />
                </div>
            </div>
        </div>

        <div class="mt-10">
            <flux:heading size="lg" class="font-display">{{ __('Fresh Bridge Posts') }}</flux:heading>
            <flux:subheading>{{ __('Real exchange happening across the platform right now.') }}</flux:subheading>
            <div class="mt-3">
                <livewire:pages::dashboard.activity :key="'dashboard-activity-'.$user->id" />
            </div>
        </div>
    </div>
</x-layouts::app>
