@php
    $seeds = $pits[$i];
    $owner = $owners[$i];
    $canPlay = in_array($i, $legalPits, true);
@endphp

<button
    type="button"
    wire:click="play({{ $i }})"
    wire:loading.attr="disabled"
    wire:target="play,startNextRound"
    @disabled(! $canPlay)
    x-bind:class="flash === {{ $i }} ? 'scale-105 ring-2 ring-stone-900 dark:ring-white' : ''"
    class="flex aspect-square flex-col items-center justify-center gap-1 rounded-lg border text-sm font-semibold transition
        {{ $canPlay
            ? 'cursor-pointer border-stone-300 bg-white hover:bg-stone-50 dark:border-stone-700 dark:bg-stone-900 dark:hover:bg-stone-800'
            : 'cursor-default border-stone-200 bg-stone-100 text-stone-400 dark:border-stone-800 dark:bg-stone-900/60 dark:text-stone-600' }}"
    data-test="ayo-pit-{{ $i }}"
>
    <span class="{{ $canPlay ? 'text-stone-900 dark:text-white' : '' }}">{{ $seeds }}</span>
    <span
        class="size-1.5 rounded-full {{ $owner === 0 ? 'bg-stone-900 dark:bg-white' : 'border border-stone-400 dark:border-stone-500' }}"
        aria-hidden="true"
    ></span>
</button>
