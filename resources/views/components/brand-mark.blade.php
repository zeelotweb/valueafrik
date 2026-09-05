@props([
    'href' => null,
    'stacked' => false,
])

@php
    $wrapperClass = $stacked
        ? 'flex flex-col items-center gap-2'
        : 'flex items-center gap-2 tracking-tight';
    $iconClass = $stacked ? 'size-9' : 'size-8';
    $textClass = $stacked ? 'text-lg tracking-tight' : '';
@endphp

<a href="{{ $href ?? route('home') }}" wire:navigate {{ $attributes->class($wrapperClass) }}>
    <x-app-logo-icon class="{{ $iconClass }} object-contain dark:invert" />
    <span class="{{ $textClass }}"><span class="font-normal text-cyan-600">value</span><span class="font-black text-stone-900 dark:text-white">AFRIK</span></span>
</a>
