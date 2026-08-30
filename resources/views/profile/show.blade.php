<?php
$profile = $user->profile;
$isOwnProfile = auth()->id() === $user->id;
$countryName = \App\Support\Countries::name($profile?->country);
$countryFlag = \App\Support\Countries::flag($profile?->country);
?>
<x-layouts::app :title="$user->name">
    <div class="mx-auto w-full max-w-3xl">
        <div class="relative">
            <div
                class="h-48 w-full overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 bg-cover bg-center dark:border-zinc-700 dark:bg-zinc-800"
                @if ($profile?->coverUrl()) style="background-image: url('{{ $profile->coverUrl() }}')" @endif
            ></div>

            <div class="absolute -bottom-10 start-6">
                <div class="size-24 overflow-hidden rounded-full border-4 border-white bg-zinc-200 shadow-sm dark:border-zinc-950 dark:bg-zinc-700">
                    @if ($profile?->avatarUrl())
                        <img src="{{ $profile->avatarUrl() }}" alt="{{ $user->name }}" class="size-full object-cover">
                    @else
                        <div class="flex size-full items-center justify-center text-zinc-500">
                            <flux:icon.user class="size-10" />
                        </div>
                    @endif
                </div>
            </div>

            @if ($isOwnProfile)
                <a href="{{ route('profile.edit') }}" wire:navigate class="absolute top-4 end-4">
                    <flux:button size="sm" variant="ghost">{{ __('Edit profile') }}</flux:button>
                </a>
            @endif
        </div>

        <div class="mt-12 px-2">
            <flux:heading size="xl">{{ $user->name }}</flux:heading>

            @if ($countryName)
                <div class="mt-1 flex items-center gap-1.5 text-sm text-zinc-500 dark:text-zinc-400">
                    <span>{{ $countryFlag }}</span>
                    <span>{{ $countryName }}</span>
                </div>
            @endif

            @if ($profile?->bio)
                <div
                    x-data="{ expanded: false, isLong: {{ Str::length($profile->bio) > 220 ? 'true' : 'false' }} }"
                    class="mt-4 max-w-xl"
                >
                    <p
                        class="text-zinc-700 dark:text-zinc-300"
                        x-bind:class="isLong && ! expanded ? 'line-clamp-3' : ''"
                    >{{ $profile->bio }}</p>

                    <button
                        type="button"
                        x-show="isLong"
                        x-on:click="expanded = ! expanded"
                        class="mt-1 text-sm font-medium text-cyan-600 hover:text-cyan-500 dark:text-cyan-400"
                    >
                        <span x-show="! expanded">{{ __('Show more') }}</span>
                        <span x-show="expanded">{{ __('Show less') }}</span>
                    </button>
                </div>
            @endif

            @if ($user->languages->isNotEmpty())
                <div class="mt-8">
                    <flux:subheading>{{ __('Languages') }}</flux:subheading>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($user->languages as $language)
                            <flux:badge variant="pill" color="zinc">{{ $language->name }}</flux:badge>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($user->heritages->isNotEmpty())
                <div class="mt-6">
                    <flux:subheading>{{ __('Heritage') }}</flux:subheading>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($user->heritages as $heritage)
                            <flux:badge variant="pill" color="cyan">{{ $heritage->name }}</flux:badge>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($user->interests->isNotEmpty())
                <div class="mt-6">
                    <flux:subheading>{{ __('Curious about') }}</flux:subheading>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($user->interests as $interest)
                            <flux:badge variant="pill" color="zinc">{{ $interest->name }}</flux:badge>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (! $profile?->bio && $user->languages->isEmpty() && $user->heritages->isEmpty() && $user->interests->isEmpty())
                <div class="mt-8 rounded-lg border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-700">
                    <flux:text>
                        @if ($isOwnProfile)
                            {{ __("You haven't filled in your Roots yet.") }}
                            <flux:link :href="route('roots.edit')" wire:navigate>{{ __('Add them now') }}</flux:link>
                        @else
                            {{ __("{$user->name} hasn't filled in their Roots yet.") }}
                        @endif
                    </flux:text>
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
