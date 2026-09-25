<?php
$showcaseItems = \App\Support\WelcomeShowcase::items();
$countries = \App\Support\WelcomeShowcase::countries();

$pillars = [
    ['type' => 'identity', 'icon' => 'user', 'title' => __('Identity & Profiles'), 'blurb' => __('Your way of life as your profile — languages, heritage, traditions, not just a bio.'), 'warm' => false],
    ['type' => 'bridge_post', 'icon' => 'chat-bubble-left-right', 'title' => __('Bridge Posts'), 'blurb' => __('Co-create posts with someone from another culture, comparing the same tradition side by side.'), 'warm' => false],
    ['type' => 'community', 'icon' => 'user-group', 'title' => __('Culture Circles'), 'blurb' => __('Small communities built around curiosity, not virality.'), 'warm' => false],
    ['type' => 'bridge_score', 'icon' => 'trophy', 'title' => __('Bridge Score & Badges'), 'blurb' => __('Recognition for sparking exchange, not just posting.'), 'warm' => true],
    ['type' => 'discovery', 'icon' => 'magnifying-glass', 'title' => __('Discovery'), 'blurb' => __('Find people through shared curiosity, not follower overlap.'), 'warm' => false],
    ['type' => 'live', 'icon' => 'video-camera', 'title' => __('Live & Video'), 'blurb' => __('Real-time conversation and broadcast, calls to cultural events.'), 'warm' => false],
];

// One accent (cyan) carries every pillar badge except Bridge Score, which
// gets the warm tone deliberately — points and badges are gold in the
// product itself, so this is the one place a second color means something
// rather than just telling pillars apart with decoration.
?>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body
        x-data="{
            modalOpen: false,
            active: 0,
            total: {{ count($showcaseItems) }},
            heroPaused: false,
            carouselInterval: null,
            startCarousel() {
                clearInterval(this.carouselInterval);

                // Re-read the real count from the DOM instead of trusting
                // this x-data's own `total` — Alpine's cross-navigation
                // morph deliberately keeps existing component state alive
                // rather than re-running x-init, so `total` (and `active`)
                // would otherwise stay frozen at whatever they were the
                // first time this session ever loaded the page. The actual
                // showcase items are random and vary per request (see
                // WelcomeShowcase::items()), so a stale `total` easily goes
                // out of sync with what is really on the page, leaving
                // `active` pointing at nothing — every item's crossfade
                // condition false at once, stuck until the interval happens
                // to wrap back into range or a hard reload resets it.
                this.total = document.querySelectorAll('[data-showcase-item]').length;
                this.active = 0;

                if (this.total > 1) {
                    this.carouselInterval = setInterval(() => {
                        if (! this.modalOpen && ! this.heroPaused) this.active = (this.active + 1) % this.total;
                    }, 5000);
                }
            },
        }"
        x-init="startCarousel(); window.addEventListener('livewire:navigated', () => startCarousel())"
        class="min-h-screen bg-stone-50 text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100"
    >
        @include('partials.marketing-header')

        <main>
            {{-- Hero --}}
            <section class="relative overflow-hidden">
                {{-- The bridge motif, drawn rather than blurred: a single
                     structural line crossing the section, echoing the hut
                     roofline in the brand mark instead of standing in for
                     "culture" with soft color blooms. Decorative, ignored by
                     screen readers. --}}
                <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
                    <svg class="absolute inset-x-0 bottom-0 h-48 w-full text-cyan-600/[0.07] dark:text-cyan-400/[0.08]" viewBox="0 0 1200 220" preserveAspectRatio="none" fill="none">
                        <path d="M0 140 L 260 140 L 420 40 L 780 40 L 940 140 L 1200 140" stroke="currentColor" stroke-width="2" />
                        <path d="M0 170 L 260 170 L 420 70 L 780 70 L 940 170 L 1200 170" stroke="currentColor" stroke-width="2" />
                    </svg>
                    <span class="absolute top-10 right-[8%] size-2 rounded-full bg-score-400/60 dark:bg-score-400/40"></span>
                    <span class="absolute top-24 right-[18%] size-1.5 rounded-full bg-cyan-500/50 dark:bg-cyan-400/40"></span>
                </div>

                <div class="mx-auto max-w-6xl px-6 pt-16 pb-16">
                    <div class="grid items-center gap-12 lg:grid-cols-2">
                        <div class="text-center lg:text-start">
                            <div class="mb-4 flex items-center justify-center gap-3 lg:justify-start">
                                <span class="text-sm font-medium tracking-widest text-cyan-600 uppercase dark:text-cyan-400">
                                    valueAFRIK
                                </span>
                                <span class="rounded-full border border-cyan-600/30 bg-cyan-50 px-2 py-0.5 text-xs font-medium text-cyan-700 dark:bg-cyan-950 dark:text-cyan-400">
                                    {{ __('Beta') }}
                                </span>
                            </div>
                            <h1 class="font-display text-5xl font-semibold tracking-tight text-balance text-stone-900 sm:text-6xl dark:text-white">
                                {{ __('Building Bridges Across Cultures') }}
                            </h1>
                            <p class="mx-auto mt-6 max-w-xl text-lg text-stone-600 lg:mx-0 dark:text-stone-400">
                                {{ __('A social platform where identity comes first and curiosity is the reason to connect — not another feed built for virality. Share who you are, discover others, and build culture together.') }}
                            </p>
                            <div class="mt-8 flex flex-wrap items-center justify-center gap-4 lg:justify-start">
                                @guest
                                    <a href="{{ route('register') }}" class="rounded-md bg-cyan-600 px-6 py-3 font-medium text-white hover:bg-cyan-500">
                                        {{ __('Join Free') }}
                                    </a>
                                    <a href="{{ route('login') }}" class="rounded-md border border-stone-300 px-6 py-3 font-medium text-stone-700 hover:border-stone-400 dark:border-stone-700 dark:text-stone-300 dark:hover:border-stone-600">
                                        {{ __('Log In') }}
                                    </a>
                                @else
                                    <a href="{{ url('/dashboard') }}" class="rounded-md bg-cyan-600 px-6 py-3 font-medium text-white hover:bg-cyan-500">
                                        {{ __('Go to Dashboard') }}
                                    </a>
                                @endguest
                            </div>
                        </div>

                        @if (count($showcaseItems) > 0)
                            <div
                                class="relative"
                                x-on:mouseenter="heroPaused = true"
                                x-on:mouseleave="heroPaused = false"
                            >
                                <span class="absolute -top-3 -left-3 z-10 flex items-center gap-1.5 rounded-full bg-stone-900 px-3 py-1 text-xs font-medium text-white shadow-sm dark:bg-white dark:text-stone-900">
                                    <span class="size-1.5 rounded-full bg-emerald-400"></span>
                                    {{ __('Real profile, live on valueAFRIK') }}
                                </span>

                                {{-- All items stacked in the same grid cell and always kept in
                                     flow (never display:none) — the grid row height is the
                                     tallest item's height, permanently, so switching which one
                                     is visible is a pure opacity crossfade with zero layout
                                     shift. x-show + x-transition on toggled siblings here would
                                     be the more obvious approach, but Alpine can get individual
                                     items' enter/leave state out of sync when several such
                                     siblings share one boolean expression (observed: two items
                                     getting stuck out of sync after the first transition) —
                                     opacity-only avoids that class of bug entirely. --}}
                                <div class="grid">
                                    @foreach ($showcaseItems as $index => $item)
                                        <div
                                            data-showcase-item
                                            class="[grid-area:1/1] transition-opacity duration-500"
                                            x-bind:class="active === {{ $index }} ? 'opacity-100' : 'opacity-0 pointer-events-none'"
                                            aria-hidden="{{ $index === 0 ? 'false' : 'true' }}"
                                            x-bind:aria-hidden="active === {{ $index }} ? 'false' : 'true'"
                                        >
                                            @include('partials.welcome-illustration', ['item' => $item])
                                        </div>
                                    @endforeach
                                </div>

                                @if (count($showcaseItems) > 1)
                                    <div class="mt-3 flex items-center justify-center gap-1.5">
                                        @foreach ($showcaseItems as $index => $item)
                                            <button
                                                type="button"
                                                x-on:click="active = {{ $index }}"
                                                aria-label="{{ __('Show showcase item :number', ['number' => $index + 1]) }}"
                                                class="size-1.5 rounded-full transition-all"
                                                x-bind:class="active === {{ $index }} ? 'w-4 bg-cyan-600 dark:bg-cyan-400' : 'bg-stone-300 dark:bg-stone-700'"
                                            ></button>
                                        @endforeach
                                    </div>
                                @endif

                                <button
                                    type="button"
                                    x-on:click="modalOpen = true"
                                    class="mt-3 flex w-full items-center justify-center gap-2 text-sm font-medium text-cyan-600 hover:text-cyan-500 dark:text-cyan-400"
                                >
                                    {{ __('See how it works') }}
                                    <flux:icon.arrow-right class="size-4" />
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            {{-- What we're not — the anti-virality pitch, made explicit instead of implicit --}}
            <section
                x-data="{ visible: false }"
                x-init="const io = new IntersectionObserver(([e]) => { if (e.isIntersecting) { visible = true; io.disconnect() } }, { threshold: 0.15 }); io.observe($el)"
                x-bind:class="{ 'is-visible': visible }"
                class="scroll-reveal bg-stone-900 py-12 dark:bg-black"
            >
                <div class="mx-auto max-w-4xl px-6">
                    <p class="text-center text-xs font-medium tracking-widest text-stone-500 uppercase">
                        {{ __('What you won\'t find here') }}
                    </p>
                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div class="flex items-center justify-center gap-2 text-stone-300">
                            <flux:icon.x-circle class="size-4 shrink-0 text-rose-500" />
                            <span>{{ __('An algorithm deciding who you see') }}</span>
                        </div>
                        <div class="flex items-center justify-center gap-2 text-stone-300">
                            <flux:icon.x-circle class="size-4 shrink-0 text-rose-500" />
                            <span>{{ __('Follower-count pressure') }}</span>
                        </div>
                        <div class="flex items-center justify-center gap-2 text-stone-300">
                            <flux:icon.x-circle class="size-4 shrink-0 text-rose-500" />
                            <span>{{ __('Infinite scroll built to keep you here') }}</span>
                        </div>
                    </div>
                    <p class="mt-6 text-center text-sm font-medium text-cyan-400">
                        {{ __('Just real people, real curiosity, and bridges worth building.') }}
                    </p>
                </div>
            </section>

            {{-- Cultures already here --}}
            @if (count($countries) > 0)
                <section
                    x-data="{ visible: false }"
                    x-init="const io = new IntersectionObserver(([e]) => { if (e.isIntersecting) { visible = true; io.disconnect() } }, { threshold: 0.15 }); io.observe($el)"
                    x-bind:class="{ 'is-visible': visible }"
                    class="scroll-reveal relative overflow-hidden border-y border-stone-200 bg-gradient-to-r from-cyan-50 via-white to-amber-50 py-8 dark:border-stone-800 dark:from-cyan-950/30 dark:via-stone-900/40 dark:to-amber-950/20"
                >
                    <div class="mx-auto flex max-w-6xl flex-col items-center gap-3 px-6 text-center">
                        <p class="text-xs font-medium tracking-widest text-stone-400 uppercase dark:text-stone-600">
                            {{ __('Already on valueAFRIK') }}
                        </p>
                        <div class="flex flex-wrap items-center justify-center gap-3">
                            @foreach ($countries as $code)
                                <span
                                    title="{{ \App\Support\Countries::name($code) }}"
                                    class="flex items-center gap-1.5 rounded-full border border-stone-200 bg-white px-3 py-1 text-sm shadow-sm dark:border-stone-800 dark:bg-stone-900"
                                >
                                    <span>{{ \App\Support\Countries::flag($code) }}</span>
                                    <span class="text-stone-600 dark:text-stone-400">{{ \App\Support\Countries::name($code) }}</span>
                                </span>
                            @endforeach
                            <span class="text-sm text-stone-400 dark:text-stone-600">{{ __('and growing every day') }}</span>
                        </div>
                    </div>
                </section>
            @endif

            {{-- Pillars --}}
            <section
                x-data="{ visible: false }"
                x-init="const io = new IntersectionObserver(([e]) => { if (e.isIntersecting) { visible = true; io.disconnect() } }, { threshold: 0.1 }); io.observe($el)"
                x-bind:class="{ 'is-visible': visible }"
                class="scroll-reveal mx-auto max-w-6xl px-6 py-20"
            >
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-medium tracking-widest text-cyan-600 uppercase dark:text-cyan-400">
                        {{ __('What you can do here') }}
                    </p>
                    <h2 class="font-display mt-2 text-3xl font-semibold tracking-tight text-balance text-stone-900 dark:text-white">
                        {{ __('Six pillars, one story') }}
                    </h2>
                    <p class="mt-4 text-stone-600 dark:text-stone-400">
                        {{ __('Everything on valueAFRIK is built to turn curiosity into a real exchange — click any piece to see it in action.') }}
                    </p>
                </div>

                <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($pillars as $index => $pillar)
                        @php $available = $index < count($showcaseItems); @endphp
                        <button
                            type="button"
                            @if ($available) x-on:click="active = {{ $index }}; modalOpen = true" @endif
                            class="surface-card group flex flex-col items-start p-6 text-start transition hover:border-cyan-600/40 disabled:opacity-60 dark:hover:border-cyan-400/40"
                            @if (! $available) disabled @endif
                        >
                            <span @class([
                                'flex size-10 items-center justify-center rounded-md',
                                'bg-score-100 text-score-600 dark:bg-score-950 dark:text-score-400' => $pillar['warm'],
                                'bg-cyan-50 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-400' => ! $pillar['warm'],
                            ])>
                                <flux:icon :icon="$pillar['icon']" class="size-5" />
                            </span>
                            <h3 class="mt-4 flex flex-wrap items-center gap-2 font-semibold text-stone-900 dark:text-white">{{ $pillar['title'] }} @if ($pillar['type'] === 'live' && ! config('features.live')) <x-coming-soon-badge /> @endif</h3>
                            <p class="mt-1.5 text-sm text-stone-500 dark:text-stone-400">{{ $pillar['blurb'] }}</p>
                            @if ($available)
                                <span class="mt-4 flex items-center gap-1 text-sm font-medium text-cyan-600 group-hover:gap-1.5 dark:text-cyan-400">
                                    {{ __('See a real example') }}
                                    <flux:icon.arrow-right class="size-3.5 transition-all" />
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </section>

            {{-- Why it's different --}}
            <section
                x-data="{ visible: false }"
                x-init="const io = new IntersectionObserver(([e]) => { if (e.isIntersecting) { visible = true; io.disconnect() } }, { threshold: 0.1 }); io.observe($el)"
                x-bind:class="{ 'is-visible': visible }"
                class="scroll-reveal relative overflow-hidden border-t border-stone-200 bg-white py-20 dark:border-stone-800 dark:bg-stone-900/40"
            >
                <div
                    class="pointer-events-none absolute inset-0 -z-10 opacity-[0.4] dark:opacity-[0.15]"
                    aria-hidden="true"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 24px 24px; color: rgb(14 116 144 / 0.15);"
                ></div>

                <div class="mx-auto max-w-6xl px-6">
                    <div class="mx-auto max-w-2xl text-center">
                        <p class="text-sm font-medium tracking-widest text-cyan-600 uppercase dark:text-cyan-400">
                            {{ __('Why it\'s different') }}
                        </p>
                        <h2 class="font-display mt-2 text-3xl font-semibold tracking-tight text-balance text-stone-900 dark:text-white">
                            {{ __('Built for exchange, not attention') }}
                        </h2>
                    </div>

                    <div class="mt-12 grid gap-5 sm:grid-cols-3">
                        <div class="surface-card p-6 text-center sm:text-start">
                            <span class="mx-auto flex size-11 items-center justify-center rounded-md bg-cyan-50 text-cyan-700 sm:mx-0 dark:bg-cyan-950 dark:text-cyan-400">
                                <flux:icon.identification class="size-5" />
                            </span>
                            <h3 class="mt-3 font-semibold text-stone-900 dark:text-white">{{ __('Identity first') }}</h3>
                            <p class="mt-1.5 text-sm text-stone-500 dark:text-stone-400">
                                {{ __('Your heritage, languages, and traditions are the profile — not an afterthought buried under a follower count.') }}
                            </p>
                        </div>
                        <div class="surface-card p-6 text-center sm:text-start">
                            <span class="mx-auto flex size-11 items-center justify-center rounded-md bg-cyan-50 text-cyan-700 sm:mx-0 dark:bg-cyan-950 dark:text-cyan-400">
                                <flux:icon.globe-europe-africa class="size-5" />
                            </span>
                            <h3 class="mt-3 font-semibold text-stone-900 dark:text-white">{{ __('Curiosity over virality') }}</h3>
                            <p class="mt-1.5 text-sm text-stone-500 dark:text-stone-400">
                                {{ __('Discovery surfaces people by shared interests and cross-cultural curiosity, not by who\'s trending.') }}
                            </p>
                        </div>
                        <div class="surface-card p-6 text-center sm:text-start">
                            <span class="mx-auto flex size-11 items-center justify-center rounded-md bg-score-100 text-score-600 sm:mx-0 dark:bg-score-950 dark:text-score-400">
                                <flux:icon.trophy class="size-5" />
                            </span>
                            <h3 class="mt-3 font-semibold text-stone-900 dark:text-white">{{ __('Recognition that means something') }}</h3>
                            <p class="mt-1.5 text-sm text-stone-500 dark:text-stone-400">
                                {{ __('Bridge Score rewards follows, conversations, and community — engagement with people, not just posts.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Final CTA --}}
            <section
                x-data="{ visible: false }"
                x-init="const io = new IntersectionObserver(([e]) => { if (e.isIntersecting) { visible = true; io.disconnect() } }, { threshold: 0.1 }); io.observe($el)"
                x-bind:class="{ 'is-visible': visible }"
                class="scroll-reveal mx-auto max-w-6xl px-6 py-20"
            >
                <div class="relative flex flex-col items-center gap-6 overflow-hidden rounded-md bg-cyan-700 px-8 py-14 text-center dark:bg-cyan-800">
                    <div class="pointer-events-none absolute inset-x-0 bottom-0 -z-10 opacity-20" aria-hidden="true">
                        <svg class="h-20 w-full" viewBox="0 0 1200 100" preserveAspectRatio="none" fill="none">
                            <path d="M0 60 L 260 60 L 420 20 L 780 20 L 940 60 L 1200 60" stroke="white" stroke-width="2" />
                        </svg>
                    </div>

                    <h2 class="font-display text-3xl font-semibold tracking-tight text-balance text-white">
                        {{ __('Come build a bridge.') }}
                    </h2>
                    <p class="max-w-md text-cyan-50">
                        {{ __('It\'s free, it\'s early, and every profile added makes the map a little bigger.') }}
                    </p>
                    @guest
                        <a href="{{ route('register') }}" class="rounded-md bg-white px-6 py-3 font-medium text-cyan-700 hover:bg-cyan-50">
                            {{ __('Join Free') }}
                        </a>
                    @else
                        <a href="{{ url('/dashboard') }}" class="rounded-md bg-white px-6 py-3 font-medium text-cyan-700 hover:bg-cyan-50">
                            {{ __('Go to Dashboard') }}
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
                            {{ __('Back') }}
                        </button>

                        <div class="flex items-center gap-1.5">
                            @foreach ($showcaseItems as $index => $item)
                                <span
                                    class="size-1.5 rounded-full"
                                    x-bind:class="active === {{ $index }} ? 'bg-cyan-500' : 'bg-stone-600'"
                                ></span>
                            @endforeach
                        </div>

                        <button
                            type="button"
                            x-on:click="active = (active + 1) % total"
                            class="rounded-md bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-500"
                        >
                            {{ __('Next') }}
                        </button>
                    </div>
                </div>
            </div>
        @endif

        @fluxScripts
    </body>
</html>
