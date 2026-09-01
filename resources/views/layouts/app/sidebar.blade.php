<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-stone-50 dark:bg-stone-900">
        <flux:sidebar
            sticky
            collapsible="mobile"
            style="--color-accent: var(--color-cyan-500); --color-accent-content: var(--color-cyan-500); --color-accent-foreground: var(--color-stone-950);"
            class="border-e border-stone-200 bg-stone-50 dark:border-stone-800 dark:bg-stone-950"
        >
            <flux:sidebar.header class="border-b border-stone-200 pb-4 dark:border-stone-800">
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="bell" :href="route('notifications.index')" :current="request()->routeIs('notifications.*')" wire:navigate>
                        {{ __('Notifications') }}
                        <livewire:pages::layout.notifications-badge :key="'notifications-badge-'.auth()->id()" />
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="chat-bubble-left-right" :href="route('messages.index')" :current="request()->routeIs('messages.*')" wire:navigate>
                        {{ __('Messages') }}
                        <livewire:pages::layout.messages-badge :key="'messages-badge-'.auth()->id()" />
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="user-group" :href="route('communities.index')" :current="request()->routeIs('communities.*')" wire:navigate>
                        {{ __('Communities') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="magnifying-glass" :href="route('discover.index')" :current="request()->routeIs('discover.*')" wire:navigate>
                        {{ __('Discover') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="video-camera" :href="route('live.index')" :current="request()->routeIs('live.*')" wire:navigate>
                        {{ __('Live') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden border-t border-stone-200 pt-4 lg:block dark:border-stone-800" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile Header -->
        <flux:header
            style="--color-accent: var(--color-cyan-500); --color-accent-content: var(--color-cyan-500); --color-accent-foreground: var(--color-stone-950);"
            class="lg:hidden border-b border-stone-200 bg-stone-50/90 backdrop-blur dark:border-stone-800 dark:bg-stone-950/90"
        >
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

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
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
