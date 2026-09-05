<?php
$pillars = [
    [
        'id' => 'identity',
        'number' => '01',
        'icon' => 'user',
        'title' => 'Identity & Profiles',
        'tagline' => 'Your way of life is your profile — not a bio and a follower count.',
        'phase' => 'Live',
        'why' => "Most platforms reduce you to a headline. valueAFRIK starts from the opposite end: the languages you speak, the heritage you carry, and the traditions that shaped you are the profile — the things that actually give someone a reason to connect with you.",
        'features' => [
            'List every heritage and language you claim — you\'re not limited to one.',
            'Write your "Roots": the story, values, and traditions behind where you\'re from.',
            'Add what you\'re curious about, so the right people find you for the right reasons.',
        ],
        'cta' => ['auth' => ['route' => 'profile.edit', 'label' => 'Edit your profile'], 'guest' => ['route' => 'register', 'label' => 'Create your profile']],
    ],
    [
        'id' => 'content',
        'number' => '02',
        'icon' => 'chat-bubble-left-right',
        'title' => 'Cultural Spotlights & Bridge Posts',
        'tagline' => 'Everyday moments, and posts built by two people at once.',
        'phase' => 'Live',
        'why' => 'A wedding photo tells one side of a story. A Bridge Post — written together by two people from different heritages, about the same tradition — tells both, side by side. That comparison is where the real curiosity lives.',
        'features' => [
            'Post to your Wall — photos, text, and moments that carry culture.',
            'Start a Bridge Post: invite someone from a different heritage to co-write about a shared theme — a wedding, a recipe, a proverb.',
            'React, comment, and bookmark on any post — the same toolkit works on your Wall and inside Communities.',
        ],
        'cta' => ['auth' => ['route' => 'dashboard', 'label' => 'Start a Bridge Post'], 'guest' => ['route' => 'register', 'label' => 'Join to post']],
    ],
    [
        'id' => 'circles',
        'number' => '03',
        'icon' => 'user-group',
        'title' => 'Culture Circles',
        'tagline' => 'Small communities built around curiosity, not virality.',
        'phase' => 'Live',
        'why' => 'A single global feed rewards whatever is loudest. Circles are scoped on purpose — Afro-diaspora food, cross-cultural entrepreneurship, music fusion — so the conversation stays close to the topic that brought people there.',
        'features' => [
            'Join public circles instantly, or request to join private ones.',
            'Followers-only circles for a smaller, trusted audience.',
            'Owners can set a circle to view-only or open it up for everyone to post.',
        ],
        'cta' => ['auth' => ['route' => 'communities.index', 'label' => 'Browse circles'], 'guest' => ['route' => 'register', 'label' => 'Join to browse circles']],
    ],
    [
        'id' => 'bridge-score',
        'number' => '04',
        'icon' => 'trophy',
        'title' => 'Bridge Score & Badges',
        'tagline' => 'Recognition for sparking exchange, not just posting.',
        'phase' => 'Live',
        'why' => 'The guiding rule behind every point value: engagement outweighs output. Posting earns a little. Genuinely connecting with someone — especially across a heritage line — earns a lot more.',
        'features' => [
            'Following someone earns a point; following across a heritage line earns a bonus.',
            'Reacting, commenting, and completing your Roots all add to your score.',
            'Your badge is always your highest score threshold — no separate list to manage.',
        ],
        'badges' => [
            ['name' => 'First Bridge', 'threshold' => 10],
            ['name' => 'Bridge Builder', 'threshold' => 50],
            ['name' => 'Culture Connector', 'threshold' => 150],
            ['name' => 'Bridge Architect', 'threshold' => 500],
        ],
        'cta' => ['auth' => ['route' => 'dashboard', 'label' => 'See your score'], 'guest' => ['route' => 'register', 'label' => 'Start earning points']],
    ],
    [
        'id' => 'discovery',
        'number' => '05',
        'icon' => 'magnifying-glass',
        'title' => 'Discovery & Matchmaking',
        'tagline' => 'Find people through shared curiosity, not follower overlap.',
        'phase' => 'Live',
        'why' => "An algorithm that only shows you people like you never actually bridges anything. Discovery is built to surface people who can widen your view — often from a different heritage than your own — alongside people who simply share what you're curious about.",
        'features' => [
            'Curated sections built around cross-heritage suggestions, not popularity.',
            'Search by name from Discover, Communities, or the header search icon.',
            'See a person\'s followers and following before you decide to connect.',
        ],
        'cta' => ['auth' => ['route' => 'discover.index', 'label' => 'Start discovering'], 'guest' => ['route' => 'register', 'label' => 'Join to discover people']],
    ],
    [
        'id' => 'live',
        'number' => '06',
        'icon' => 'video-camera',
        'title' => 'Live & Video',
        'tagline' => 'Real-time conversation and broadcast.',
        'phase' => 'Early access',
        'why' => 'Text and photos carry a lot of culture, but some of it only comes through live — a conversation, a performance, a room full of people asking questions in real time. This pillar is where we\'re building next.',
        'features' => [
            'Start a stream straight from your Dashboard.',
            'Watch what\'s live right now from the Live tab.',
            'Calls, mentorship sessions, and cultural events are next on the roadmap.',
        ],
        'cta' => ['auth' => ['route' => 'live.index', 'label' => 'See what\'s live'], 'guest' => ['route' => 'register', 'label' => 'Join to go live']],
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
                    New here?
                </p>
                <h1 class="text-4xl font-bold tracking-tight text-balance sm:text-5xl">
                    Six pillars. One idea.
                </h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg text-stone-600 dark:text-stone-400">
                    valueAFRIK is built around one belief: curiosity about someone else's culture is worth
                    more than another follower. Everything below is a different way we put that into
                    practice — start wherever looks most interesting.
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
                        @php
                            $ctaConfig = auth()->check() ? $pillar['cta']['auth'] : $pillar['cta']['guest'];
                        @endphp
                        <div id="{{ $pillar['id'] }}" class="scroll-mt-32 py-14">
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-sm text-stone-400 dark:text-stone-600">{{ $pillar['number'] }}</span>
                                <span class="flex size-9 items-center justify-center rounded-lg bg-cyan-50 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-400">
                                    <flux:icon :icon="$pillar['icon']" class="size-5" />
                                </span>
                                <span class="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-medium text-cyan-700 dark:bg-cyan-950 dark:text-cyan-400">
                                    {{ $pillar['phase'] }}
                                </span>
                            </div>

                            <h2 class="mt-4 text-2xl font-bold tracking-tight">{{ $pillar['title'] }}</h2>
                            <p class="mt-1 text-stone-500 dark:text-stone-400">{{ $pillar['tagline'] }}</p>

                            <p class="mt-5 leading-relaxed text-stone-600 dark:text-stone-400">{{ $pillar['why'] }}</p>

                            <ul class="mt-5 space-y-2.5">
                                @foreach ($pillar['features'] as $feature)
                                    <li class="flex items-start gap-2.5 text-sm text-stone-700 dark:text-stone-300">
                                        <flux:icon.check class="mt-0.5 size-4 shrink-0 text-cyan-600 dark:text-cyan-400" />
                                        <span>{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            @if (isset($pillar['badges']))
                                <div class="mt-6 flex flex-wrap gap-2">
                                    @foreach ($pillar['badges'] as $badge)
                                        <span class="rounded-full border border-stone-200 bg-white px-3 py-1.5 text-xs font-medium text-stone-600 dark:border-stone-800 dark:bg-stone-900 dark:text-stone-400">
                                            {{ $badge['name'] }}
                                            <span class="text-stone-400 dark:text-stone-600">· {{ $badge['threshold'] }}+</span>
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            <a
                                href="{{ route($ctaConfig['route']) }}"
                                wire:navigate
                                class="mt-6 inline-flex items-center gap-1.5 text-sm font-medium text-cyan-600 hover:text-cyan-500 dark:text-cyan-400"
                            >
                                {{ $ctaConfig['label'] }}
                                <flux:icon.arrow-right class="size-3.5" />
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="mx-auto max-w-4xl px-6 py-16 text-center">
                @auth
                    <h2 class="text-2xl font-bold tracking-tight">Your bridges are waiting to be built.</h2>
                    <p class="mx-auto mt-3 max-w-xl text-stone-600 dark:text-stone-400">
                        Head back to your dashboard and pick up wherever you left off.
                    </p>
                    <a href="{{ route('dashboard') }}" wire:navigate class="mt-6 inline-flex items-center gap-2 rounded-md bg-cyan-600 px-5 py-2.5 font-medium text-white hover:bg-cyan-500">
                        Go to your dashboard
                        <flux:icon.arrow-right class="size-4" />
                    </a>
                @else
                    <h2 class="text-2xl font-bold tracking-tight">Ready to build your first bridge?</h2>
                    <p class="mx-auto mt-3 max-w-xl text-stone-600 dark:text-stone-400">
                        It starts with a profile — your languages, your heritage, your curiosity.
                    </p>
                    <a href="{{ route('register') }}" class="mt-6 inline-flex items-center gap-2 rounded-md bg-cyan-600 px-5 py-2.5 font-medium text-white hover:bg-cyan-500">
                        Join Free
                        <flux:icon.arrow-right class="size-4" />
                    </a>
                @endauth
            </section>
        </main>

        @include('partials.marketing-footer')

        @fluxScripts
    </body>
</html>
