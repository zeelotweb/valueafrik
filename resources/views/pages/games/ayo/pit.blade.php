@php
    $seeds = $pits[$i];
    $owner = $owners[$i];
    $canPlay = in_array($i, $legalPits, true);
    $isOpeningPit = in_array($i, $openingPit, true);

    // A single branch per combination, rather than layering competing
    // border-color utilities, so the opening-pit marker can't lose a class
    // ordering tie against the normal enabled/disabled border.
    $stateClasses = match (true) {
        $isOpeningPit && $canPlay => 'cursor-pointer border-2 border-green-500 bg-white hover:bg-stone-50 dark:border-green-400 dark:bg-stone-900 dark:hover:bg-stone-800',
        $isOpeningPit => 'cursor-default border-2 border-green-500 bg-stone-100 text-stone-400 dark:border-green-400 dark:bg-stone-900/60 dark:text-stone-600',
        $canPlay => 'cursor-pointer border-stone-300 bg-white hover:bg-stone-50 dark:border-stone-700 dark:bg-stone-900 dark:hover:bg-stone-800',
        default => 'cursor-default border-stone-200 bg-stone-100 text-stone-400 dark:border-stone-800 dark:bg-stone-900/60 dark:text-stone-600',
    };
@endphp

<button
    type="button"
    wire:click="play({{ $i }})"
    wire:loading.attr="disabled"
    wire:target="play,startNextRound"
    @disabled(! $canPlay)
    x-bind:class="flash === {{ $i }} ? 'scale-105 ring-2 ring-stone-900 dark:ring-white' : ''"
    class="flex aspect-square flex-col items-center justify-center gap-1 rounded-lg border text-sm font-semibold transition {{ $stateClasses }}"
    data-test="ayo-pit-{{ $i }}"
    @if ($isOpeningPit) title="{{ __('Opened the round here') }}" @endif
>
    <span class="{{ $canPlay ? 'text-stone-900 dark:text-white' : '' }}">{{ $seeds }}</span>
    <span
        class="size-1.5 rounded-full {{ $owner === 0 ? 'bg-stone-900 dark:bg-white' : 'border border-stone-400 dark:border-stone-500' }}"
        aria-hidden="true"
    ></span>
</button>
