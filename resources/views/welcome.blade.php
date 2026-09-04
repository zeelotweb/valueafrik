<?php
$showcaseItems = \App\Support\WelcomeShowcase::items();
$heroItem = count($showcaseItems) > 0 ? $showcaseItems[array_rand($showcaseItems)] : null;
$countries = \App\Support\WelcomeShowcase::countries();

$pillars = [
    ['type' => 'identity', 'icon' => 'user', 'title' => 'Identity & Profiles', 'blurb' => 'Your way of life as your profile — languages, heritage, traditions, not just a bio.'],
    ['type' => 'bridge_post', 'icon' => 'chat-bubble-left-right', 'title' => 'Bridge Posts', 'blurb' => 'Co-create posts with someone from another culture, comparing the same tradition side by side.'],
    ['type' => 'community', 'icon' => 'user-group', 'title' => 'Culture Circles', 'blurb' => 'Small communities built around curiosity, not virality.'],
    ['type' => 'bridge_score', 'icon' => 'trophy', 'title' => 'Bridge Score & Badges', 'blurb' => 'Recognition for sparking exchange, not just posting.'],
    ['type' => 'discovery', 'icon' => 'magnifying-glass', 'title' => 'Discovery', 'blurb' => 'Find people through shared curiosity, not follower overlap.'],
    ['type' => 'live', 'icon' => 'video-camera', 'title' => 'Live & Video', 'blurb' => 'Real-time conversation and broadcast, calls to cultural events.'],
];
?>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body
        x-data="{ modalOpen: false, active: 0, total: {{ count($showcaseItems) }} }"
        x-on:keydown.escape.window="modalOpen = false"
        class="min-h-screen bg-stone-50 text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100"
    >
        @include('partials.marketing-header')

        <main>
            {{-- Hero --}}
            <section class="mx-auto max-w-6xl px-6 pt-16 pb-16">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div class="text-center lg:text-start">
                        <div class="mb-4 flex items-center justify-center gap-3 lg:justify-start">
                            <span class="text-sm font-medium tracking-widest text-green-600 uppercase dark:text-green-400">
                                valueAFRIK
                            </span>
                            <span class="rounded-full border border-green-600/30 bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-950 dark:text-green-400">
                                Beta
                            </span>
                        </div>
                        <h1 class="text-4xl font-bold tracking-tight text-balance sm:text-5xl">
                            Building Bridges Across Cultures
                        </h1>
                        <p class="mx-auto mt-6 max-w-xl text-lg text-stone-600 lg:mx-0 dark:text-stone-400">
                            A social platform where identity comes first and curiosity is the reason to connect —
                            not another feed built for virality. Share who you are, discover others, and build
                            culture together.
                        </p>
                        <div class="mt-8 flex flex-wrap items-center justify-center gap-4 lg:justify-start">
                            @guest
                                <a href="{{ route('register') }}" class="rounded-md bg-green-600 px-6 py-3 font-medium text-white hover:bg-green-500">
                                    Join Free
                                </a>
                                <a href="{{ route('login') }}" class="rounded-md border border-stone-300 px-6 py-3 font-medium text-stone-700 hover:border-stone-400 dark:border-stone-700 dark:text-stone-300 dark:hover:border-stone-600">
                                    Log In
                                </a>
                            @else
                                <a href="{{ url('/dashboard') }}" class="rounded-md bg-green-600 px-6 py-3 font-medium text-white hover:bg-green-500">
                                    Go to Dashboard
                                </a>
                            @endguest
                        </div>
                    </div>

                    @if ($heroItem)
                        <div>
                            @include('partials.welcome-illustration', ['item' => $heroItem])

                            <button
                                type="button"
                                x-on:click="active = 0; modalOpen = true"
                                class="mt-4 flex w-full items-center justify-center gap-2 text-sm font-medium text-green-600 hover:text-green-500 dark:text-green-400"
                            >
                                See how it works
                                <flux:icon.arrow-right class="size-4" />
                            </button>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Cultures already here --}}
            @if (count($countries) > 0)
                <section class="border-y border-stone-200 bg-white py-8 dark:border-stone-800 dark:bg-stone-900/40">
                    <div class="mx-auto flex max-w-6xl flex-col items-center gap-3 px-6 text-center">
                        <p class="text-xs font-medium tracking-widest text-stone-400 uppercase dark:text-stone-600">
                            Already on valueAFRIK
                        </p>
                        <div class="flex flex-wrap items-center justify-center gap-3">
                            @foreach ($countries as $code)
                                <span
                                    title="{{ \App\Support\Countries::name($code) }}"
                                    class="flex items-center gap-1.5 rounded-full border border-stone-200 bg-stone-50 px-3 py-1 text-sm dark:border-stone-800 dark:bg-stone-900"
                                >
                                    <span>{{ \App\Support\Countries::flag($code) }}</span>
                                    <span class="text-stone-600 dark:text-stone-400">{{ \App\Support\Countries::name($code) }}</span>
                                </span>
                            @endforeach
                            <span class="text-sm text-stone-400 dark:text-stone-600">and growing every day</span>
                        </div>
                    </div>
                </section>
            @endif

            {{-- Pillars --}}
            <section class="mx-auto max-w-6xl px-6 py-20">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-medium tracking-widest text-green-600 uppercase dark:text-green-400">
                        What you can do here
                    </p>
                    <h2 class="mt-2 text-3xl font-bold tracking-tight text-balance">
                        Six pillars, one story
                    </h2>
                    <p class="mt-4 text-stone-600 dark:text-stone-400">
                        Everything on valueAFRIK is built to turn curiosity into a real exchange — click any
                        piece to see it in action.
                    </p>
                </div>

                <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($pillars as $index => $pillar)
                        @php $available = $index < count($showcaseItems); @endphp
                        <button
                            type="button"
                            @if ($available) x-on:click="active = {{ $index }}; modalOpen = true" @endif
                            class="group flex flex-col items-start rounded-2xl border border-stone-200 bg-white p-6 text-start transition hover:border-green-600/40 hover:shadow-sm disabled:opacity-60 dark:border-stone-800 dark:bg-stone-900"
                            @if (! $available) disabled @endif
                        >
                            <span class="flex size-10 items-center justify-center rounded-lg bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-400">
                                <flux:icon :icon="$pillar['icon']" class="size-5" />
                            </span>
                            <h3 class="mt-4 font-semibold">{{ $pillar['title'] }}</h3>
                            <p class="mt-1.5 text-sm text-stone-500 dark:text-stone-400">{{ $pillar['blurb'] }}</p>
                            @if ($available)
                                <span class="mt-4 flex items-center gap-1 text-sm font-medium text-green-600 group-hover:gap-1.5 dark:text-green-400">
                                    See a real example
                                    <flux:icon.arrow-right class="size-3.5 transition-all" />
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </section>

            {{-- Why it's different --}}
            <section class="border-t border-stone-200 bg-white py-20 dark:border-stone-800 dark:bg-stone-900/40">
                <div class="mx-auto max-w-6xl px-6">
                    <div class="mx-auto max-w-2xl text-center">
                        <p class="text-sm font-medium tracking-widest text-green-600 uppercase dark:text-green-400">
                            Why it's different
                        </p>
                        <h2 class="mt-2 text-3xl font-bold tracking-tight text-balance">
                            Built for exchange, not attention
                        </h2>
                    </div>

                    <div class="mt-12 grid gap-8 sm:grid-cols-3">
                        <div class="text-center sm:text-start">
                            <flux:icon.identification class="mx-auto size-6 text-green-600 sm:mx-0 dark:text-green-400" />
                            <h3 class="mt-3 font-semibold">Identity first</h3>
                            <p class="mt-1.5 text-sm text-stone-500 dark:text-stone-400">
                                Your heritage, languages, and traditions are the profile — not an afterthought
                                buried under a follower count.
                            </p>
                        </div>
                        <div class="text-center sm:text-start">
                            <flux:icon.globe-europe-africa class="mx-auto size-6 text-green-600 sm:mx-0 dark:text-green-400" />
                            <h3 class="mt-3 font-semibold">Curiosity over virality</h3>
                            <p class="mt-1.5 text-sm text-stone-500 dark:text-stone-400">
                                Discovery surfaces people by shared interests and cross-cultural curiosity, not
                                by who's trending.
                            </p>
                        </div>
                        <div class="text-center sm:text-start">
                            <flux:icon.trophy class="mx-auto size-6 text-green-600 sm:mx-0 dark:text-green-400" />
                            <h3 class="mt-3 font-semibold">Recognition that means something</h3>
                            <p class="mt-1.5 text-sm text-stone-500 dark:text-stone-400">
                                Bridge Score rewards follows, conversations, and community — engagement with
                                people, not just posts.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Final CTA --}}
            <section class="mx-auto max-w-6xl px-6 py-20">
                <div class="flex flex-col items-center gap-6 rounded-3xl border border-stone-200 bg-white px-8 py-14 text-center dark:border-stone-800 dark:bg-stone-900">
                    <h2 class="text-3xl font-bold tracking-tight text-balance">
                        Come build a bridge.
                    </h2>
                    <p class="max-w-md text-stone-600 dark:text-stone-400">
                        It's free, it's early, and every profile added makes the map a little bigger.
                    </p>
                    @guest
                        <a href="{{ route('register') }}" class="rounded-md bg-green-600 px-6 py-3 font-medium text-white hover:bg-green-500">
                            Join Free
                        </a>
                    @else
                        <a href="{{ url('/dashboard') }}" class="rounded-md bg-green-600 px-6 py-3 font-medium text-white hover:bg-green-500">
                            Go to Dashboard
                        </a>
                    @endguest
                </div>
            </section>
        </main>

        @include('partials.marketing-footer')

        @if (count($showcaseItems) > 0)
            <div
                x-show="modalOpen"
                x-transition.opacity
                style="display: none;"
                class="fixed inset-0 z-50 flex items-center justify-center bg-stone-950/70 p-4"
                x-on:click.self="modalOpen = false"
            >
                <div class="relative w-full max-w-lg">
                    <button
                        type="button"
                        x-on:click="modalOpen = false"
                        class="absolute -top-10 right-0 text-stone-300 hover:text-white"
                    >
                        <flux:icon.x-mark class="size-6" />
                    </button>

                    @foreach ($showcaseItems as $index => $item)
                        <div x-show="active === {{ $index }}">
                            @include('partials.welcome-illustration', ['item' => $item])
                        </div>
                    @endforeach

                    <div class="mt-4 flex items-center justify-between">
                        <button
                            type="button"
                            x-on:click="active = (active - 1 + total) % total"
                            class="rounded-md border border-stone-700 px-4 py-2 text-sm font-medium text-stone-200 hover:border-stone-500"
                        >
                            Back
                        </button>

                        <div class="flex items-center gap-1.5">
                            @foreach ($showcaseItems as $index => $item)
                                <span
                                    class="size-1.5 rounded-full"
                                    x-bind:class="active === {{ $index }} ? 'bg-green-500' : 'bg-stone-600'"
                                ></span>
                            @endforeach
                        </div>

                        <button
                            type="button"
                            x-on:click="active = (active + 1) % total"
                            class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-500"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        @endif

        @fluxScripts
    </body>
</html>
