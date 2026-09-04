<?php
$user = auth()->user();
$badge = $user->bridgeBadge();
$rootsIncomplete = ! $user->profile?->bio || $user->languages->isEmpty() || $user->heritages->isEmpty();
?>
<x-layouts::app :title="__('Dashboard')">
    <div class="mx-auto w-full max-w-5xl">
        <div class="flex items-center justify-between">
            <flux:heading size="l">
                {{ __(' :name', ['name' => Str::before($user->name, ' ')]) }}
            </flux:heading>

            <livewire:pages::dashboard.start-stream :key="'start-stream-'.$user->id" />
        </div>

        @if ($rootsIncomplete)
            <div class="mt-4 flex items-center justify-between rounded-lg border border-dashed border-stone-300 p-4 dark:border-stone-700">
                <flux:text>{{ __("You haven't finished your Roots yet — it's how people find common ground with you.") }}</flux:text>
                <flux:link :href="route('roots.edit')" wire:navigate class="shrink-0">{{ __('Finish it') }}</flux:link>
            </div>
        @endif

        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <a
                href="{{ route('profile.show', $user) }}"
                wire:navigate
                class="rounded-xl border border-stone-200 p-5 hover:bg-stone-50 dark:border-stone-800 dark:hover:bg-stone-800"
            >
                <div class="flex items-center gap-2 text-cyan-600 dark:text-cyan-400">
                    <flux:icon.sparkles class="size-5" />
                    <span class="text-sm font-medium">{{ __('Bridge Score') }}</span>
                </div>
                <p class="mt-2 text-3xl font-semibold text-stone-900 dark:text-white">{{ $user->bridgeScore() }}</p>
                <p class="mt-1 truncate text-sm text-stone-500 dark:text-stone-400">
                    {{ $badge['name'] ?? __('Just getting started') }}
                </p>
            </a>

            <a
                href="{{ route('communities.index') }}"
                wire:navigate
                class="rounded-xl border border-stone-200 p-5 hover:bg-stone-50 dark:border-stone-800 dark:hover:bg-stone-800"
            >
                <div class="flex items-center gap-2 text-cyan-600 dark:text-cyan-400">
                    <flux:icon.user-group class="size-5" />
                    <span class="text-sm font-medium">{{ __('Communities') }}</span>
                </div>
                <p class="mt-2 text-3xl font-semibold text-stone-900 dark:text-white">
                    {{ $user->communities()->wherePivot('status', 'active')->count() }}
                </p>
                <p class="mt-1 text-sm text-stone-500 dark:text-stone-400">{{ __('joined') }}</p>
            </a>

            <a
                href="{{ route('messages.index') }}"
                wire:navigate
                class="rounded-xl border border-stone-200 p-5 hover:bg-stone-50 dark:border-stone-800 dark:hover:bg-stone-800"
            >
                <div class="flex items-center gap-2 text-cyan-600 dark:text-cyan-400">
                    <flux:icon.chat-bubble-left-right class="size-5" />
                    <span class="text-sm font-medium">{{ __('Messages') }}</span>
                </div>
                <p class="mt-2 text-3xl font-semibold text-stone-900 dark:text-white">{{ $user->unreadConversationsCount() }}</p>
                <p class="mt-1 text-sm text-stone-500 dark:text-stone-400">{{ __('unread') }}</p>
            </a>
        </div>

        <div class="mt-10 grid gap-8 lg:grid-cols-2">
            <div>
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Needs your attention') }}</flux:heading>
                </div>

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
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Your communities') }}</flux:heading>
                    <a href="{{ route('communities.create') }}" wire:navigate class="text-sm font-medium text-cyan-600 hover:text-cyan-500 dark:text-cyan-400">
                        {{ __('New community') }}
                    </a>
                </div>
                <div class="mt-3">
                    <livewire:pages::profile.communities-list :user="$user" :key="'dashboard-communities-'.$user->id" />
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
