<?php
$pillars = [
    [
        'id' => 'identity',
        'number' => '01',
        'title' => 'Identity & Profiles',
        'summary' => 'Your way of life as your profile — languages, heritage, traditions — not just a bio and a follower count.',
        'phase' => 'Phase 1',
    ],
    [
        'id' => 'content',
        'number' => '02',
        'title' => 'Cultural Spotlights & Bridge Posts',
        'summary' => 'Share the moments that carry culture — a wedding, a recipe, a festival — and co-create posts comparing the same tradition across two cultures.',
        'phase' => 'Phase 1',
    ],
    [
        'id' => 'circles',
        'number' => '03',
        'title' => 'Culture Circles',
        'summary' => 'Small communities built around curiosity, not virality — Afro-diaspora food, cross-cultural entrepreneurship, music fusion.',
        'phase' => 'Phase 2',
    ],
    [
        'id' => 'bridge-score',
        'number' => '04',
        'title' => 'Bridge Score & Badges',
        'summary' => 'Recognition for sparking exchange, not just posting — a visible measure of how many bridges you\'ve built.',
        'phase' => 'Phase 2',
    ],
    [
        'id' => 'discovery',
        'number' => '05',
        'title' => 'Discovery & Matchmaking',
        'summary' => 'Find people through shared curiosity, not follower overlap — a geo-cultural map and curiosity-based recommendations.',
        'phase' => 'Phase 3',
    ],
    [
        'id' => 'live',
        'number' => '06',
        'title' => 'Live & Video',
        'summary' => 'Real-time conversation and broadcast — mentorship sessions, cultural events, dialogue rooms — built on a proper SFU from day one.',
        'phase' => 'Cross-phase',
    ],
];
?>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-stone-50 text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100">

        <header class="sticky top-0 z-20 border-b border-stone-200/80 bg-stone-50/90 backdrop-blur dark:border-stone-800/80 dark:bg-stone-950/90">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold tracking-tight" wire:navigate>
                    <img src="{{ asset('apple-touch-icon-180x180.png') }}" alt="valueAFRIK" class="size-8 dark:invert">
                    <span><span class="text-cyan-600">value</span><span class="text-stone-900 dark:text-white">AFRIK</span></span>
                </a>

                <nav class="hidden items-center gap-6 text-sm text-stone-600 lg:flex dark:text-stone-400">
                    <a href="#platform" class="hover:text-stone-900 dark:hover:text-white">The Platform</a>
                </nav>

                <div class="flex items-center gap-3 text-sm">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="rounded-md bg-stone-900 px-4 py-2 font-medium text-white hover:bg-stone-700 dark:bg-white dark:text-stone-900 dark:hover:bg-stone-200">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-white">
                            Log in
                        </a>
                        <a href="{{ route('register') }}" class="rounded-md bg-cyan-600 px-4 py-2 font-medium text-white hover:bg-cyan-500">
                            Join Free
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <main>
            <section class="mx-auto max-w-4xl px-6 pt-20 pb-16 text-center">
                <p class="mb-4 text-sm font-medium tracking-widest text-cyan-600 uppercase dark:text-cyan-400">
                    valueAFRIK
                </p>
                <h1 class="text-4xl font-bold tracking-tight text-balance sm:text-5xl">
                    Building Bridges Across Cultures
                </h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg text-stone-600 dark:text-stone-400">
                    A social platform where identity comes first and curiosity is the reason to connect —
                    not another feed built for virality. Share who you are, discover others, and build
                    culture together.
                </p>
                <div class="mt-8 flex items-center justify-center gap-4">
                    @guest
                        <a href="{{ route('register') }}" class="rounded-md bg-cyan-600 px-6 py-3 font-medium text-white hover:bg-cyan-500">
                            Join Free
                        </a>
                        <a href="{{ route('login') }}" class="rounded-md border border-stone-300 px-6 py-3 font-medium text-stone-700 hover:border-stone-400 dark:border-stone-700 dark:text-stone-300 dark:hover:border-stone-600">
                            Log In
                        </a>
                    @else
                        <a href="{{ url('/dashboard') }}" class="rounded-md bg-cyan-600 px-6 py-3 font-medium text-white hover:bg-cyan-500">
                            Go to Dashboard
                        </a>
                    @endguest
                </div>
            </section>

            <section id="platform" class="border-t border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
                <div class="mx-auto max-w-6xl px-6 py-20">
                    <div class="mb-12 max-w-2xl">
                        <h2 class="text-sm font-medium tracking-widest text-cyan-600 uppercase dark:text-cyan-400">
                            The Platform
                        </h2>
                        <p class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
                            Six pillars, built in order.
                        </p>
                        <p class="mt-3 text-stone-600 dark:text-stone-400">
                            This is the map we're building against — identity and content first, then
                            community and recognition, then discovery. Each section below will link to
                            the real thing as it ships.
                        </p>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($pillars as $pillar)
                            <div id="{{ $pillar['id'] }}" class="scroll-mt-24 rounded-xl border border-stone-200 p-6 dark:border-stone-800">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-sm text-stone-400 dark:text-stone-600">{{ $pillar['number'] }}</span>
                                    <span class="rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-600 dark:bg-stone-800 dark:text-stone-400">
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

        <footer class="border-t border-stone-200 dark:border-stone-800">
            <div class="mx-auto flex max-w-6xl flex-col items-center gap-3 px-6 py-10 text-sm text-stone-500 sm:flex-row sm:justify-between dark:text-stone-500">
                <span>valueAFRIK</span>
                <span>🚧 Beta: This platform is under active development. Expect changes as we build together.</span>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
