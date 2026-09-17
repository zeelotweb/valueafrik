<header class="sticky top-0 z-20 border-b border-stone-200/80 bg-stone-100/90 backdrop-blur dark:border-stone-800/80 dark:bg-black/90">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
        <x-brand-mark />

        <nav class="hidden items-center gap-6 text-sm text-stone-600 lg:flex dark:text-stone-400">
            <a href="{{ route('guide') }}" class="hover:text-stone-900 dark:hover:text-white" wire:navigate>Guide</a>
        </nav>

        <div class="flex items-center gap-3 text-sm">
            @auth
                <a href="{{ url('/dashboard') }}" title="Dashboard" class="btn-flat-primary flex size-9 items-center justify-center rounded-md">
                    <flux:icon.squares-2x2 class="size-5" />
                </a>

                <flux:dropdown position="bottom" align="end">
                    <flux:profile icon-trailing="chevron-down">
                        <x-slot:avatar>
                            <flux:avatar size="sm" circle :src="auth()->user()->profile?->avatarUrl()" />
                        </x-slot:avatar>
                    </flux:profile>

                    <flux:menu>
                        <flux:menu.radio.group>
                            <div class="p-0 text-sm font-normal">
                                <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                    <flux:avatar circle :src="auth()->user()->profile?->avatarUrl()" />

                                    <div class="grid flex-1 text-start text-sm leading-tight">
                                        <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                        <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                    </div>
                                </div>
                            </div>
                        </flux:menu.radio.group>

                        <flux:menu.separator />

                        <flux:menu.radio.group>
                            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                                {{ __('Settings') }}
                            </flux:menu.item>
                        </flux:menu.radio.group>

                        <flux:menu.separator />

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item
                                as="button"
                                type="submit"
                                icon="arrow-right-start-on-rectangle"
                                class="w-full cursor-pointer"
                                data-test="logout-button"
                            >
                                {{ __('Log out') }}
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
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
