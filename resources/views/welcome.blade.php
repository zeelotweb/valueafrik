<?php
$showcaseItems = \App\Support\WelcomeShowcase::items();
$heroItem = $showcaseItems[array_rand($showcaseItems)] ?? null;
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
            <section class="mx-auto max-w-6xl px-6 pt-16 pb-20">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div class="text-center lg:text-start">
                        <p class="mb-4 text-sm font-medium tracking-widest text-cyan-600 uppercase dark:text-cyan-400">
                            valueAFRIK
                        </p>
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

                    @if ($heroItem)
                        <div>
                            @include('partials.welcome-illustration', ['item' => $heroItem])

                            <button
                                type="button"
                                x-on:click="active = 0; modalOpen = true"
                                class="mt-4 flex w-full items-center justify-center gap-2 text-sm font-medium text-cyan-600 hover:text-cyan-500 dark:text-cyan-400"
                            >
                                See how it works
                                <flux:icon.arrow-right class="size-4" />
                            </button>
                        </div>
                    @endif
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
