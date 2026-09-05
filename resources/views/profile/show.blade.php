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
                class="h-40 w-full overflow-hidden rounded-xl border border-stone-200 bg-stone-100 bg-cover bg-center sm:h-48 dark:border-stone-800 dark:bg-stone-800"
                @if ($profile?->coverUrl()) style="background-image: url('{{ $profile->coverUrl() }}')" @endif
            ></div>

            <div class="absolute -bottom-12 start-4 sm:start-6">
                <div class="size-24 overflow-hidden rounded-full bg-stone-200 shadow-xl sm:size-28 dark:bg-stone-700">
                    @if ($profile?->avatarUrl())
                        <img src="{{ $profile->avatarUrl() }}" alt="{{ $user->name }}" class="size-full object-cover">
                    @else
                        <div class="flex size-full items-center justify-center text-stone-500">
                            <flux:icon.user class="size-10 sm:size-12" />
                        </div>
                    @endif
                </div>
            </div>

            <div class="absolute top-3 end-3 flex items-center gap-1.5 sm:top-4 sm:end-4 sm:gap-2">
                @if ($isOwnProfile)
                    <a href="{{ route('profile.edit') }}" wire:navigate>
                        <flux:button size="sm" variant="ghost" icon="pencil" class="!bg-white/90 !text-stone-900 shadow-sm backdrop-blur hover:!bg-white dark:!bg-stone-900/80 dark:!text-white dark:hover:!bg-stone-900">
                            <span class="hidden sm:inline">{{ __('Edit profile') }}</span>
                        </flux:button>
                    </a>
                @else
                    <livewire:pages::profile.start-call-button :user="$user" :key="'call-'.$user->id" :overlay="true" />
                    <livewire:pages::profile.message-button :user="$user" :key="'message-'.$user->id" :overlay="true" />
                    <livewire:pages::profile.follow-button :user="$user" :key="'follow-'.$user->id" :overlay="true" />
                @endif
            </div>
        </div>

        <div class="mt-12 px-2">
            <flux:heading size="xl">{{ $user->name }}</flux:heading>

            @if ($countryName)
                <div class="mt-1 flex items-center gap-1.5 text-sm text-stone-500 dark:text-stone-400">
                    <span>{{ $countryFlag }}</span>
                    <span>{{ $countryName }}</span>
                </div>
            @endif

            <livewire:pages::profile.follower-counts :user="$user" :key="'counts-'.$user->id" />

            @php
                $bridgeScore = $user->bridgeScore();
                $bridgeBadge = $user->bridgeBadge();
            @endphp

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <flux:badge variant="pill" color="rose" icon="sparkles">
                    {{ trans_choice('1 bridge point|:count bridge points', $bridgeScore) }}
                </flux:badge>

                @if ($bridgeBadge)
                    <flux:badge variant="pill" color="zinc">{{ $bridgeBadge['name'] }}</flux:badge>
                @endif
            </div>

            @if ($profile?->bio)
                <div
                    x-data="{ expanded: false, isLong: {{ Str::length($profile->bio) > 220 ? 'true' : 'false' }} }"
                    class="mt-4 max-w-xl"
                >
                    <p
                        class="text-stone-700 dark:text-stone-300"
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
                <div class="mt-8 rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-700">
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

            <div x-data="{ tab: 'wall' }" class="mt-10 border-t border-stone-200 pt-8 dark:border-stone-800">
                <div class="flex items-center gap-6 border-b border-stone-200 dark:border-stone-800">
                    <button
                        type="button"
                        x-on:click="tab = 'wall'"
                        x-bind:class="tab === 'wall' ? 'border-cyan-600 text-cyan-600' : 'border-transparent text-stone-500 hover:text-stone-700 dark:hover:text-stone-300'"
                        class="border-b-2 pb-3 text-sm font-medium"
                    >
                        {{ __('Wall') }}
                    </button>
                    <button
                        type="button"
                        x-on:click="tab = 'communities'"
                        x-bind:class="tab === 'communities' ? 'border-cyan-600 text-cyan-600' : 'border-transparent text-stone-500 hover:text-stone-700 dark:hover:text-stone-300'"
                        class="border-b-2 pb-3 text-sm font-medium"
                    >
                        {{ __('Communities') }}
                    </button>
                </div>

                <div x-show="tab === 'wall'" class="mt-4">
                    @if ($isOwnProfile)
                        <livewire:pages::profile.wall-composer :key="'wall-composer-'.$user->id" />
                        <livewire:pages::profile.bridge-post-composer :key="'bridge-post-composer-'.$user->id" />
                    @endif

                    <livewire:pages::profile.bridge-posts :user="$user" :key="'bridge-posts-'.$user->id" />

                    <livewire:pages::profile.wall-posts :user="$user" :key="'wall-posts-'.$user->id" />
                </div>

                <div x-show="tab === 'communities'" class="mt-4">
                    <livewire:pages::profile.communities-list :user="$user" :key="'communities-list-'.$user->id" />
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
