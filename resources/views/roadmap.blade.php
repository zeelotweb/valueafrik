<?php
$pillars = [
    [
        'id' => 'identity',
        'number' => '01',
        'icon' => 'user',
        'title' => 'Identity & Profiles',
        'summary' => 'Three things you fill in become the profile — there\'s no separate "about me" to write.',
        'steps' => [
            'You add your languages, the heritage(s) you claim, and what you\'re curious about.',
            'Those three fields are the entire profile — nothing else is required to be visible.',
            'Anyone who visits your profile sees exactly those fields, plus your Bridge Score.',
        ],
    ],
    [
        'id' => 'bridge-posts',
        'number' => '02',
        'icon' => 'chat-bubble-left-right',
        'title' => 'Bridge Posts',
        'summary' => 'One post, written by two people who never see each other\'s draft until both are done.',
        'steps' => [
            'One person invites another and picks a shared theme — a wedding, a recipe, a proverb.',
            'Each side writes their own half independently; neither can read the other\'s side yet.',
            'The moment both sides are in, the post goes live as one card, split down the middle.',
        ],
    ],
    [
        'id' => 'circles',
        'number' => '03',
        'icon' => 'user-group',
        'title' => 'Culture Circles',
        'summary' => 'Two ways in, depending on how the owner set the circle up.',
        'steps' => [
            'A public circle: you tap Join and you\'re in immediately, no approval step.',
            'A private circle: you send a request, and it sits pending until the owner acts on it.',
            'The owner approves or declines; only an approval actually adds you as a member.',
        ],
    ],
    [
        'id' => 'bridge-score',
        'number' => '04',
        'icon' => 'trophy',
        'title' => 'Bridge Score & Badges',
        'summary' => 'Every action has a fixed point value. Cross a threshold, and your badge updates automatically.',
        'steps' => [
            'Actions score differently on purpose: a reaction is worth 1, completing a Bridge Post is worth 5.',
            'Points accumulate into a single running total — there\'s no separate list of "achievements."',
            'Your badge is always whichever threshold your total has crossed — it updates the instant you cross it.',
        ],
    ],
    [
        'id' => 'discovery',
        'number' => '05',
        'icon' => 'magnifying-glass',
        'title' => 'Discovery & Matchmaking',
        'summary' => 'Two signals overlap to decide who gets suggested to you.',
        'steps' => [
            'The system looks at what you\'ve said you\'re curious about.',
            'It looks separately at whose heritage differs from yours.',
            'People who satisfy both at once are the ones surfaced first — shared curiosity, different background.',
        ],
    ],
    [
        'id' => 'live',
        'number' => '06',
        'icon' => 'video-camera',
        'title' => 'Live & Video',
        'summary' => 'Two different mechanics under one tab: a 1:1 ring, and a broadcast anyone can walk into.',
        'steps' => [
            'A call rings the other person for a fixed window; if they don\'t pick up, it resolves to missed.',
            'A stream is one-to-many: you go live, and anyone on the platform can join as a viewer.',
            'A streamer can bring a viewer on as a collaborator mid-broadcast — that\'s the one moment the two mechanics meet.',
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-stone-50 text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100">

        @include('partials.marketing-header')

        <main>
            <section class="mx-auto max-w-4xl px-6 pt-16 pb-8 text-center">
                <p class="mb-4 text-sm font-medium tracking-widest text-cyan-600 uppercase dark:text-cyan-400">
                    How it works
                </p>
                <h1 class="text-4xl font-bold tracking-tight text-balance sm:text-5xl">
                    Roadmap
                </h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg text-stone-600 dark:text-stone-400">
                    Not a pitch — a map of the mechanics. Six things exist on valueAFRIK, and here's
                    exactly how each one moves from an action you take to something that happens.
                </p>
            </section>

            <nav class="sticky top-[65px] z-10 border-y border-stone-200 bg-stone-50/90 backdrop-blur dark:border-stone-800 dark:bg-stone-950/90">
                <div class="mx-auto flex max-w-6xl gap-1 overflow-x-auto px-6 py-3 text-sm">
                    @foreach ($pillars as $pillar)
                        <a
                            href="#{{ $pillar['id'] }}"
                            class="flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 font-medium text-stone-600 hover:bg-stone-200 dark:text-stone-400 dark:hover:bg-stone-800"
                        >
                            <flux:icon :icon="$pillar['icon']" class="size-4" />
                            {{ $pillar['title'] }}
                        </a>
                    @endforeach
                </div>
            </nav>

            <section class="border-b border-stone-200 dark:border-stone-800">
                <div class="mx-auto max-w-4xl divide-y divide-stone-200 px-6 dark:divide-stone-800">
                    @foreach ($pillars as $pillar)
                        <div id="{{ $pillar['id'] }}" class="scroll-mt-32 py-14">
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-sm text-stone-400 dark:text-stone-600">{{ $pillar['number'] }}</span>
                                <span class="flex size-9 items-center justify-center rounded-lg bg-cyan-50 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-400">
                                    <flux:icon :icon="$pillar['icon']" class="size-5" />
                                </span>
                            </div>

                            <h2 class="mt-4 text-2xl font-bold tracking-tight">{{ $pillar['title'] }}</h2>
                            <p class="mt-1 text-stone-500 dark:text-stone-400">{{ $pillar['summary'] }}</p>

                            <div class="mt-8">
                                @include('partials.roadmap-diagram-' . $pillar['id'])
                            </div>

                            <ol class="mt-8 space-y-4">
                                @foreach ($pillar['steps'] as $index => $step)
                                    <li class="flex items-start gap-3 text-sm text-stone-700 dark:text-stone-300">
                                        <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-stone-200 font-mono text-[11px] font-semibold text-stone-600 dark:bg-stone-800 dark:text-stone-400">
                                            {{ $index + 1 }}
                                        </span>
                                        <span class="leading-relaxed">{{ $step }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="mx-auto max-w-4xl px-6 py-16 text-center">
                <p class="text-stone-500 dark:text-stone-400">
                    Curious what each of these looks like to use, not just how it works?
                    <a href="{{ route('guide') }}" wire:navigate class="font-medium text-cyan-600 hover:text-cyan-500 dark:text-cyan-400">
                        Read the Guide
                    </a>
                    instead.
                </p>
            </section>
        </main>

        @include('partials.marketing-footer')

        @fluxScripts
    </body>
</html>
