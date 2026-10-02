@php
    $seeds = $pits[$i];
    $owner = $owners[$i];
    $canPlay = in_array($i, $legalPits, true);
    $isLastMovePit = in_array($i, $lastMovePit, true);

    // Background says whose pit this is — amber for seat 0, sky for seat 1 —
    // independent of whose turn it currently is, since ownership itself can
    // shift between rounds and that's worth seeing at a glance regardless of
    // who's up. Border/cursor/text still carry the separate enabled vs
    // disabled signal, and the last-move marker still overrides the border
    // color specifically, so the three signals (owner, playable, last move)
    // never fight for the same pixel.
    $ownerBg = $owner === 0
        ? ($canPlay
            ? 'bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/40 dark:hover:bg-amber-950/60'
            : 'bg-amber-50/60 dark:bg-amber-950/20')
        : ($canPlay
            ? 'bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/40 dark:hover:bg-sky-950/60'
            : 'bg-sky-50/60 dark:bg-sky-950/20');

    $borderClasses = match (true) {
        $isLastMovePit && $canPlay => 'cursor-pointer border-2 border-green-500 dark:border-green-400',
        $isLastMovePit => 'cursor-default border-2 border-green-500 dark:border-green-400',
        $canPlay => 'cursor-pointer border-stone-300 dark:border-stone-700',
        default => 'cursor-default border-stone-200 dark:border-stone-800',
    };

    $textClasses = $canPlay ? 'text-stone-900 dark:text-white' : 'text-stone-500 dark:text-stone-400';

    $dotClasses = $owner === 0
        ? 'bg-amber-600 dark:bg-amber-400'
        : 'bg-sky-600 dark:bg-sky-400';
@endphp

<button
    type="button"
    wire:click="play({{ $i }})"
    wire:loading.attr="disabled"
    wire:target="play,startNextRound"
    @disabled(! $canPlay)
    x-bind:class="flash === {{ $i }} ? 'scale-105 ring-2 ring-stone-900 dark:ring-white' : ''"
    class="flex aspect-square flex-col items-center justify-center gap-1 rounded-lg border text-sm font-semibold transition {{ $borderClasses }} {{ $ownerBg }}"
    data-test="ayo-pit-{{ $i }}"
    @if ($isLastMovePit) title="{{ __("This side's last move started here") }}" @endif
>
    <span
        class="{{ $textClasses }}"
        x-text="(animating && overridePits[{{ $i }}] !== undefined) ? overridePits[{{ $i }}] : {{ $seeds }}"
    >{{ $seeds }}</span>
    <span class="size-1.5 rounded-full {{ $dotClasses }}" aria-hidden="true"></span>
</button>
