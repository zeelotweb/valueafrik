<header class="sticky top-0 z-20 border-b border-stone-200/80 bg-stone-50/90 backdrop-blur dark:border-stone-800/80 dark:bg-stone-950/90">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
        <a href="/" class="flex items-center gap-2 font-semibold tracking-tight" wire:navigate>
            <img src="{{ asset('apple-touch-icon-180x180.png') }}" alt="valueAFRIK" class="size-8 dark:invert">
            <span><span class="text-green-600">value</span><span class="text-stone-900 dark:text-white">AFRIK</span></span>
        </a>

        <nav class="hidden items-center gap-6 text-sm text-stone-600 lg:flex dark:text-stone-400">
            <a href="{{ route('guide') }}" class="hover:text-stone-900 dark:hover:text-white" wire:navigate>Guide</a>
        </nav>

        <div class="flex items-center gap-3 text-sm">
            @auth
                <a href="{{ url('/dashboard') }}" title="Dashboard" class="flex size-9 items-center justify-center rounded-md bg-stone-900 text-white hover:bg-stone-700 dark:bg-white dark:text-stone-900 dark:hover:bg-stone-200">
                    <flux:icon.home class="size-5" />
                </a>
            @else
                <a href="{{ route('login') }}" class="text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-white">
                    Log in
                </a>
                <a href="{{ route('register') }}" class="rounded-md bg-green-600 px-4 py-2 font-medium text-white hover:bg-green-500">
                    Join Free
                </a>
            @endauth
        </div>
    </div>
</header>
