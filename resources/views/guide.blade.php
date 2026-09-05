<?php
$pillars = [
    [
        'id' => 'identity',
        'number' => '01',
        'title' => 'Identity & Profiles',
        'summary' => 'Your way of life as your profile — languages, heritage, traditions — not just a bio and a follower count.',
        'phase' => 'Live',
    ],
    [
        'id' => 'content',
        'number' => '02',
        'title' => 'Cultural Spotlights & Bridge Posts',
        'summary' => 'Share the moments that carry culture — a wedding, a recipe, a festival — and co-create posts comparing the same tradition across two cultures.',
        'phase' => 'Live',
    ],
    [
        'id' => 'circles',
        'number' => '03',
        'title' => 'Culture Circles',
        'summary' => 'Small communities built around curiosity, not virality — Afro-diaspora food, cross-cultural entrepreneurship, music fusion.',
        'phase' => 'Live',
    ],
    [
        'id' => 'bridge-score',
        'number' => '04',
        'title' => 'Bridge Score & Badges',
        'summary' => 'Recognition for sparking exchange, not just posting — a visible measure of how many bridges you\'ve built.',
        'phase' => 'Live',
    ],
    [
        'id' => 'discovery',
        'number' => '05',
        'title' => 'Discovery & Matchmaking',
        'summary' => 'Find people through shared curiosity, not follower overlap — surfaced by what you\'re curious about and who can widen your view.',
        'phase' => 'Live',
    ],
    [
        'id' => 'live',
        'number' => '06',
        'title' => 'Live & Video',
        'summary' => 'Real-time conversation and broadcast — calls, streams, and eventually mentorship sessions and cultural events.',
        'phase' => 'Early',
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
                    The Guide
                </p>
                <h1 class="text-4xl font-bold tracking-tight text-balance sm:text-5xl">
                    Six pillars, built in order.
                </h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg text-stone-600 dark:text-stone-400">
                    This is the map we're building against — identity and content first, then community
                    and recognition, then discovery and real-time connection. Here's where each piece
                    stands.
                </p>
            </section>

            <section class="border-t border-stone-200 dark:border-stone-800">
                <div class="mx-auto max-w-6xl px-6 py-16">
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($pillars as $pillar)
                            <div id="{{ $pillar['id'] }}" class="scroll-mt-24 rounded-xl bg-white border border-stone-200 p-6 dark:bg-stone-900 dark:border-stone-800">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-sm text-stone-400 dark:text-stone-600">{{ $pillar['number'] }}</span>
                                    <span class="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-medium text-cyan-700 dark:bg-cyan-950 dark:text-cyan-400">
                                        {{ $pillar['phase'] }}
                                    </span>
                                </div>
                                <h3 class="mt-4 font-semibold">{{ $pillar['title'] }}</h3>
                                <p class="mt-2 text-sm text-stone-600 dark:text-stone-400">{{ $pillar['summary'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </main>

        @include('partials.marketing-footer')

        @fluxScripts
    </body>
</html>
