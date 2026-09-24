@props([
    // 'header': compact icon button with the language code, sized like its neighbours.
    // 'footer': quiet text link that matches the footer's other links.
    'variant' => 'header',
    // Element id to return to after switching, so the footer doesn't throw you to the top.
    'anchor' => null,
])

{{-- Interface language menu. Each language is a plain link (full page load) so
     the new language and fonts apply everywhere at once. --}}
@php
    $current = app()->getLocale();
    $languages = config('locales.available');
    $native = $languages[$current]['native'] ?? $current;
    $isFooter = $variant === 'footer';
@endphp

<flux:dropdown :position="$isFooter ? 'top' : 'bottom'" align="end">
    @if ($isFooter)
        <button
            type="button"
            aria-label="{{ __('Language') }}: {{ $native }}"
            class="inline-flex items-center gap-1.5 text-stone-500 transition-colors hover:text-stone-900 dark:text-stone-400 dark:hover:text-white"
        >
            <flux:icon.language class="size-4 shrink-0" />
            <span lang="{{ str_replace('_', '-', $current) }}">{{ $native }}</span>
        </button>
    @else
        {{-- Code, not the full name: every language takes the same room, so the
             top bar doesn't shift when the language changes. --}}
        <button
            type="button"
            aria-label="{{ __('Language') }}: {{ $native }}"
            title="{{ $languages[$current]['name'] ?? $current }}"
            class="inline-flex h-9 items-center gap-1 rounded-lg px-2 text-stone-600 transition-colors hover:bg-stone-200 dark:text-stone-300 dark:hover:bg-stone-800"
            data-test="language-switcher"
        >
            <flux:icon.language class="size-5 shrink-0" />
            <span class="text-xs font-medium tracking-wide uppercase">{{ strtok($current, '_') }}</span>
        </button>
    @endif

    <flux:menu class="w-60">
        @foreach ($languages as $code => $language)
            <flux:menu.item
                :href="route('locale.update', array_filter(['locale' => $code, 'anchor' => $anchor]))"
                :icon="$code === $current ? 'check' : null"
                :aria-current="$code === $current ? 'true' : null"
                lang="{{ str_replace('_', '-', $code) }}"
                hreflang="{{ str_replace('_', '-', $code) }}"
            >
                {{ $language['native'] }}
            </flux:menu.item>
        @endforeach

        <flux:menu.separator />

        <p class="px-3 py-2 text-xs leading-relaxed text-stone-400">
            {{ __('Translations are in beta — some wording may be off.') }}
        </p>
    </flux:menu>
</flux:dropdown>
