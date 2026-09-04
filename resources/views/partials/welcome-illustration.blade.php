@php
    $countryFlag = fn ($code) => $code ? \App\Support\Countries::flag($code) : null;
@endphp

<div class="flex h-full flex-col rounded-2xl border border-stone-200 bg-white p-6 shadow-sm dark:border-stone-800 dark:bg-stone-900">
    <div class="flex items-center justify-between">
        <span class="font-mono text-xs text-stone-400 dark:text-stone-600">{{ $item['number'] }}</span>
        <span class="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-medium text-cyan-700 dark:bg-cyan-950 dark:text-cyan-400">
            {{ $item['title'] }}
        </span>
    </div>

    <div class="mt-5 flex-1">
        @switch($item['type'])
            @case('identity')
                @php $profile = $item['user']->profile; @endphp
                <div class="flex items-center gap-3">
                    <div class="size-14 shrink-0 overflow-hidden rounded-full bg-stone-100 dark:bg-stone-800">
                        @if ($profile?->avatarUrl())
                            <img src="{{ $profile->avatarUrl() }}" class="size-full object-cover" alt="">
                        @else
                            <div class="flex size-full items-center justify-center text-stone-400">
                                <flux:icon.user class="size-6" />
                            </div>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $item['user']->name }}</p>
                        <p class="truncate text-sm text-stone-500 dark:text-stone-400">
                            {{ $countryFlag($profile?->country) }} {{ $item['user']->heritages->pluck('name')->join(', ') }}
                        </p>
                    </div>
                </div>
                <p class="mt-4 line-clamp-3 text-sm text-stone-600 dark:text-stone-400">{{ $profile?->bio }}</p>
                <div class="mt-4 flex flex-wrap gap-1.5">
                    @foreach ($item['user']->languages->take(3) as $language)
                        <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-600 dark:bg-stone-800 dark:text-stone-400">{{ $language->name }}</span>
                    @endforeach
                </div>
            @break

            @case('bridge_post')
                @php $post = $item['post']; @endphp
                <p class="text-sm font-medium text-stone-500 dark:text-stone-400">"{{ $post->theme }}"</p>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    @foreach ([$post->initiator, $post->partner] as $side)
                        <div class="rounded-lg bg-stone-50 p-3 dark:bg-stone-800/60">
                            <div class="flex items-center gap-2">
                                <div class="size-7 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                                    @if ($side->profile?->avatarUrl())
                                        <img src="{{ $side->profile->avatarUrl() }}" class="size-full object-cover" alt="">
                                    @endif
                                </div>
                                <span class="truncate text-xs font-medium">{{ $side->name }}</span>
                            </div>
                            <p class="mt-2 line-clamp-3 text-xs text-stone-600 dark:text-stone-400">
                                {{ $side->id === $post->initiator_id ? $post->initiator_body : $post->partner_body }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @break

            @case('community')
                @php $community = $item['community']; @endphp
                <div class="flex items-center gap-3">
                    <div class="size-14 shrink-0 overflow-hidden rounded-xl bg-stone-100 dark:bg-stone-800">
                        @if ($community->avatarUrl())
                            <img src="{{ $community->avatarUrl() }}" class="size-full object-cover" alt="">
                        @else
                            <div class="flex size-full items-center justify-center text-stone-400">
                                <flux:icon.user-group class="size-6" />
                            </div>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $community->name }}</p>
                        <p class="text-sm text-stone-500 dark:text-stone-400">
                            {{ trans_choice('1 member|:count members', $community->active_members_count) }}
                        </p>
                    </div>
                </div>
                <p class="mt-4 line-clamp-3 text-sm text-stone-600 dark:text-stone-400">{{ $community->description }}</p>
            @break

            @case('bridge_score')
                @php $badge = $item['user']->bridgeBadge(); @endphp
                <div class="flex items-center gap-3">
                    <div class="size-14 shrink-0 overflow-hidden rounded-full bg-stone-100 dark:bg-stone-800">
                        @if ($item['user']->profile?->avatarUrl())
                            <img src="{{ $item['user']->profile->avatarUrl() }}" class="size-full object-cover" alt="">
                        @else
                            <div class="flex size-full items-center justify-center text-stone-400">
                                <flux:icon.user class="size-6" />
                            </div>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $item['user']->name }}</p>
                        <p class="text-sm text-cyan-600 dark:text-cyan-400">{{ $badge['name'] ?? '' }}</p>
                    </div>
                </div>
                <p class="mt-4 text-4xl font-bold tracking-tight text-cyan-600 dark:text-cyan-400">
                    {{ $item['user']->bridgeScore() }}
                </p>
                <p class="mt-1 text-sm text-stone-500 dark:text-stone-400">bridge points earned through real exchange</p>
            @break

            @case('discovery')
                <div class="flex items-center justify-center gap-3">
                    @foreach ([$item['first'], $item['second']] as $person)
                        <div class="size-14 shrink-0 overflow-hidden rounded-full bg-stone-100 dark:bg-stone-800">
                            @if ($person->profile?->avatarUrl())
                                <img src="{{ $person->profile->avatarUrl() }}" class="size-full object-cover" alt="">
                            @else
                                <div class="flex size-full items-center justify-center text-stone-400">
                                    <flux:icon.user class="size-6" />
                                </div>
                            @endif
                        </div>
                        @if ($person->is($item['first']))
                            <flux:icon.arrows-right-left class="size-5 shrink-0 text-cyan-600 dark:text-cyan-400" />
                        @endif
                    @endforeach
                </div>
                <p class="mt-4 text-center text-sm">
                    <span class="font-medium">{{ $item['first']->name }}</span> &amp;
                    <span class="font-medium">{{ $item['second']->name }}</span>
                </p>
                <p class="mt-1 text-center text-sm text-stone-500 dark:text-stone-400">
                    both curious about <span class="font-medium text-cyan-600 dark:text-cyan-400">{{ $item['interest']->name }}</span>
                </p>
            @break

            @case('live')
                @php $session = $item['session']; @endphp
                <div class="flex aspect-video items-center justify-center rounded-lg bg-stone-900">
                    <flux:icon.video-camera class="size-10 text-stone-500" />
                </div>
                <p class="mt-4 font-medium">{{ $session->title }}</p>
                <p class="mt-1 text-sm text-stone-500 dark:text-stone-400">hosted by {{ $session->host->name }}</p>
            @break
        @endswitch
    </div>
</div>
