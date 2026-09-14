<header class="sticky top-0 z-20 border-b border-stone-200/80 bg-stone-100/90 backdrop-blur dark:border-stone-800/80 dark:bg-black/90">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
        <x-brand-mark />

        <nav class="hidden items-center gap-6 text-sm text-stone-600 lg:flex dark:text-stone-400">
            <a href="{{ route('guide') }}" class="hover:text-stone-900 dark:hover:text-white" wire:navigate>Guide</a>
        </nav>

        <div class="flex items-center gap-3 text-sm">
            @auth
                <a href="{{ url('/dashboard') }}" title="Dashboard" class="btn-flat-primary flex size-9 items-center justify-center rounded-md">
                    <flux:icon.home class="size-5" />
                </a>
                <a
                    href="{{ route('profile.edit') }}"
                    wire:navigate
                    title="Settings"
                    class="flex size-9 items-center justify-center rounded-md text-stone-500 hover:bg-stone-200 hover:text-stone-900 dark:text-stone-400 dark:hover:bg-stone-800 dark:hover:text-white"
                >
                    <flux:icon.cog-6-tooth class="size-5" />
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        title="Log out"
                        class="flex size-9 items-center justify-center rounded-md text-stone-500 hover:bg-stone-200 hover:text-stone-900 dark:text-stone-400 dark:hover:bg-stone-800 dark:hover:text-white"
                    >
                        <flux:icon.arrow-right-start-on-rectangle class="size-5" />
                    </button>
                </form>
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
