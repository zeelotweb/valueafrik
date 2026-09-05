@php
    $user = auth()->user();
@endphp

<div class="flex items-start gap-8 max-md:flex-col">
    <div class="w-full pb-4 md:w-[240px] md:shrink-0">
        <div class="flex items-center gap-3 rounded-xl bg-white border border-stone-200 p-3 dark:bg-stone-900 dark:border-stone-800">
            <div class="size-11 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                @if ($user->profile?->avatarUrl())
                    <img src="{{ $user->profile->avatarUrl() }}" class="size-full object-cover">
                @else
                    <div class="flex size-full items-center justify-center text-stone-500">
                        <flux:icon.user class="size-5" />
                    </div>
                @endif
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-stone-900 dark:text-white">{{ $user->name }}</p>
                <p class="truncate text-xs text-stone-500 dark:text-stone-400">{{ $user->email }}</p>
            </div>
        </div>

        <nav class="mt-4 space-y-0.5 rounded-xl bg-white border border-stone-200 p-2 dark:bg-stone-900 dark:border-stone-800">
            @foreach ([
                ['route' => 'profile.edit', 'label' => __('Profile'), 'icon' => 'user'],
                ['route' => 'roots.edit', 'label' => __('Roots'), 'icon' => 'identification'],
                ['route' => 'security.edit', 'label' => __('Security'), 'icon' => 'shield-check'],
                ['route' => 'appearance.edit', 'label' => __('Appearance'), 'icon' => 'swatch'],
            ] as $item)
                @php $isCurrent = request()->routeIs($item['route']); @endphp
                <a
                    href="{{ route($item['route']) }}"
                    wire:navigate
                    class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition {{ $isCurrent ? 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/50 dark:text-cyan-400' : 'text-stone-600 hover:bg-stone-100 dark:text-stone-400 dark:hover:bg-stone-800' }}"
                >
                    <flux:icon :icon="$item['icon']" class="size-4.5" />
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </div>

    <div class="min-w-0 flex-1 self-stretch">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
