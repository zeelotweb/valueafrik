<?php
$showcaseItems = \App\Support\WelcomeShowcase::items();
$countries = \App\Support\WelcomeShowcase::countries();

$pillars = [
    ['type' => 'identity', 'icon' => 'user', 'title' => 'Identity & Profiles', 'blurb' => 'Your way of life as your profile — languages, heritage, traditions, not just a bio.', 'color' => 'cyan'],
    ['type' => 'bridge_post', 'icon' => 'chat-bubble-left-right', 'title' => 'Bridge Posts', 'blurb' => 'Co-create posts with someone from another culture, comparing the same tradition side by side.', 'color' => 'rose'],
    ['type' => 'community', 'icon' => 'user-group', 'title' => 'Culture Circles', 'blurb' => 'Small communities built around curiosity, not virality.', 'color' => 'amber'],
    ['type' => 'bridge_score', 'icon' => 'trophy', 'title' => 'Bridge Score & Badges', 'blurb' => 'Recognition for sparking exchange, not just posting.', 'color' => 'violet'],
    ['type' => 'discovery', 'icon' => 'magnifying-glass', 'title' => 'Discovery', 'blurb' => 'Find people through shared curiosity, not follower overlap.', 'color' => 'emerald'],
    ['type' => 'live', 'icon' => 'video-camera', 'title' => 'Live & Video', 'blurb' => 'Real-time conversation and broadcast, calls to cultural events.', 'color' => 'orange'],
];

// Literal Tailwind class strings per color, keyed by the pillar 'color'
// above — kept as full strings (not string-built) so Tailwind's scanner
// picks them all up.
$pillarColors = [
    'cyan' => ['badge' => 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-400', 'ring' => 'group-hover:border-cyan-600/40'],
    'rose' => ['badge' => 'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-400', 'ring' => 'group-hover:border-rose-600/40'],
    'amber' => ['badge' => 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-400', 'ring' => 'group-hover:border-amber-600/40'],
    'violet' => ['badge' => 'bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-400', 'ring' => 'group-hover:border-violet-600/40'],
    'emerald' => ['badge' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400', 'ring' => 'group-hover:border-emerald-600/40'],
    'orange' => ['badge' => 'bg-orange-50 text-orange-700 dark:bg-orange-950 dark:text-orange-400', 'ring' => 'group-hover:border-orange-600/40'],
];
?>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body
        x-data="{ modalOpen: false, active: 0, total: {{ count($showcaseItems) }}, heroPaused: false }"
        x-on:keydown.escape.window="modalOpen = false"
        x-init="if (total > 1) { setInterval(() => { if (! modalOpen && ! heroPaused) active = (active + 1) % total }, 5000) }"
        class="min-h-screen bg-stone-50 text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100"
    >
        @include('partials.marketing-header')

        <main>
            {{-- Hero --}}
            <section class="relative overflow-hidden">
                {{-- Aurora backdrop: soft color blooms standing in for a literal
                     "bridge" — warm meeting cool, nobody depicted, so nothing to
                     misrepresent. Blurred, decorative, ignored by screen readers. --}}
                <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
                    <div class="animate-drift absolute -top-24 -left-24 size-80 rounded-full bg-cyan-300/50 blur-3xl dark:bg-cyan-700/20"></div>
                    <div class="animate-drift absolute top-4 -right-16 size-96 rounded-full bg-amber-300/40 blur-3xl dark:bg-amber-700/15" style="animation-delay: -5s"></div>
                    <div class="animate-drift absolute bottom-0 left-1/3 size-72 rounded-full bg-rose-300/30 blur-3xl dark:bg-rose-800/15" style="animation-delay: -9s"></div>

                    <svg class="absolute inset-x-0 bottom-0 h-40 w-full text-cyan-600/10 dark:text-cyan-400/10" viewBox="0 0 1200 200" preserveAspectRatio="none" fill="none">
                        <path d="M0 160 Q 300 40 600 160 T 1200 160" stroke="currentColor" stroke-width="2" />
                        <path d="M0 180 Q 300 70 600 180 T 1200 180" stroke="currentColor" stroke-width="2" />
                    </svg>
                </div>

                <div class="mx-auto max-w-6xl px-6 pt-16 pb-16">
                    <div class="grid items-center gap-12 lg:grid-cols-2">
                        <div class="text-center lg:text-start">
                            <div class="mb-4 flex items-center justify-center gap-3 lg:justify-start">
                                <span class="text-sm font-medium tracking-widest text-cyan-600 uppercase dark:text-cyan-400">
                                    valueAFRIK
                                </span>
                                <span class="rounded-full border border-cyan-600/30 bg-cyan-50 px-2 py-0.5 text-xs font-medium text-cyan-700 dark:bg-cyan-950 dark:text-cyan-400">
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
                        </div>

                        @if (count($showcaseItems) > 0)
                            <div
                                class="relative"
                                x-on:mouseenter="heroPaused = true"
                                x-on:mouseleave="heroPaused = false"
                            >
                                <span class="absolute -top-3 -left-3 z-10 flex items-center gap-1.5 rounded-full bg-stone-900 px-3 py-1 text-xs font-medium text-white shadow-sm dark:bg-white dark:text-stone-900">
                                    <span class="size-1.5 rounded-full bg-emerald-400"></span>
                                    Real profile, live on valueAFRIK
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
                                                aria-label="Show showcase item {{ $index + 1 }}"
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
                                    See how it works
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
                        What you won't find here
                    </p>
                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div class="flex items-center justify-center gap-2 text-stone-300">
                            <flux:icon.x-circle class="size-4 shrink-0 text-rose-500" />
                            <span>An algorithm deciding who you see</span>
                        </div>
                        <div class="flex items-center justify-center gap-2 text-stone-300">
                            <flux:icon.x-circle class="size-4 shrink-0 text-rose-500" />
                            <span>Follower-count pressure</span>
                        </div>
                        <div class="flex items-center justify-center gap-2 text-stone-300">
                            <flux:icon.x-circle class="size-4 shrink-0 text-rose-500" />
                            <span>Infinite scroll built to keep you here</span>
                        </div>
                    </div>
                    <p class="mt-6 text-center text-sm font-medium text-cyan-400">
                        Just real people, real curiosity, and bridges worth building.
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
                            Already on valueAFRIK
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
                            <span class="text-sm text-stone-400 dark:text-stone-600">and growing every day</span>
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
                        @php
                            $available = $index < count($showcaseItems);
                            $colors = $pillarColors[$pillar['color']];
                        @endphp
                        <button
                            type="button"
                            @if ($available) x-on:click="active = {{ $index }}; modalOpen = true" @endif
                            class="group flex flex-col items-start rounded-2xl border border-stone-200 bg-white p-6 text-start transition hover:-translate-y-0.5 hover:shadow-md disabled:opacity-60 disabled:hover:translate-y-0 {{ $colors['ring'] }} dark:border-stone-800 dark:bg-stone-900"
                            @if (! $available) disabled @endif
                        >
                            <span class="flex size-10 items-center justify-center rounded-lg {{ $colors['badge'] }}">
                                <flux:icon :icon="$pillar['icon']" class="size-5" />
                            </span>
                            <h3 class="mt-4 font-semibold">{{ $pillar['title'] }}</h3>
                            <p class="mt-1.5 text-sm text-stone-500 dark:text-stone-400">{{ $pillar['blurb'] }}</p>
                            @if ($available)
                                <span class="mt-4 flex items-center gap-1 text-sm font-medium text-cyan-600 group-hover:gap-1.5 dark:text-cyan-400">
                                    See a real example
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
                            Why it's different
                        </p>
                        <h2 class="mt-2 text-3xl font-bold tracking-tight text-balance">
                            Built for exchange, not attention
                        </h2>
                    </div>

                    <div class="mt-12 grid gap-8 sm:grid-cols-3">
                        <div class="rounded-2xl bg-white p-6 text-center shadow-sm sm:text-start dark:bg-stone-900">
                            <span class="mx-auto flex size-11 items-center justify-center rounded-lg bg-cyan-50 text-cyan-700 sm:mx-0 dark:bg-cyan-950 dark:text-cyan-400">
                                <flux:icon.identification class="size-5" />
                            </span>
                            <h3 class="mt-3 font-semibold">Identity first</h3>
                            <p class="mt-1.5 text-sm text-stone-500 dark:text-stone-400">
                                Your heritage, languages, and traditions are the profile — not an afterthought
                                buried under a follower count.
                            </p>
                        </div>
                        <div class="rounded-2xl bg-white p-6 text-center shadow-sm sm:text-start dark:bg-stone-900">
                            <span class="mx-auto flex size-11 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 sm:mx-0 dark:bg-emerald-950 dark:text-emerald-400">
                                <flux:icon.globe-europe-africa class="size-5" />
                            </span>
                            <h3 class="mt-3 font-semibold">Curiosity over virality</h3>
                            <p class="mt-1.5 text-sm text-stone-500 dark:text-stone-400">
                                Discovery surfaces people by shared interests and cross-cultural curiosity, not
                                by who's trending.
                            </p>
                        </div>
                        <div class="rounded-2xl bg-white p-6 text-center shadow-sm sm:text-start dark:bg-stone-900">
                            <span class="mx-auto flex size-11 items-center justify-center rounded-lg bg-violet-50 text-violet-700 sm:mx-0 dark:bg-violet-950 dark:text-violet-400">
                                <flux:icon.trophy class="size-5" />
                            </span>
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
            <section
                x-data="{ visible: false }"
                x-init="const io = new IntersectionObserver(([e]) => { if (e.isIntersecting) { visible = true; io.disconnect() } }, { threshold: 0.1 }); io.observe($el)"
                x-bind:class="{ 'is-visible': visible }"
                class="scroll-reveal mx-auto max-w-6xl px-6 py-20"
            >
                <div class="relative flex flex-col items-center gap-6 overflow-hidden rounded-3xl bg-gradient-to-br from-cyan-600 to-cyan-800 px-8 py-14 text-center shadow-lg">
                    <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
                        <div class="absolute -top-10 -right-10 size-56 rounded-full bg-amber-400/20 blur-3xl"></div>
                        <div class="absolute -bottom-16 -left-10 size-64 rounded-full bg-white/10 blur-3xl"></div>
                    </div>

                    <h2 class="text-3xl font-bold tracking-tight text-balance text-white">
                        Come build a bridge.
                    </h2>
                    <p class="max-w-md text-cyan-50">
                        It's free, it's early, and every profile added makes the map a little bigger.
                    </p>
                    @guest
                        <a href="{{ route('register') }}" class="rounded-md bg-white px-6 py-3 font-medium text-cyan-700 hover:bg-cyan-50">
                            Join Free
                        </a>
                    @else
                        <a href="{{ url('/dashboard') }}" class="rounded-md bg-white px-6 py-3 font-medium text-cyan-700 hover:bg-cyan-50">
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
                                    x-bind:class="active === {{ $index }} ? 'bg-cyan-500' : 'bg-stone-600'"
                                ></span>
                            @endforeach
                        </div>

                        <button
                            type="button"
                            x-on:click="active = (active + 1) % total"
                            class="rounded-md bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-500"
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
