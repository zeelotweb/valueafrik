@php $text ??= ''; @endphp
<div
    x-data="{ expanded: false, overflowing: false }"
    x-init="$nextTick(() => overflowing = $refs.clampedText.scrollHeight > $refs.clampedText.clientHeight + 1)"
>
    <p
        x-ref="clampedText"
        x-bind:class="expanded ? '' : 'line-clamp-2'"
        class="whitespace-pre-line text-sm text-stone-700 dark:text-stone-300"
    >{{ $text }}</p>
    <button
        type="button"
        x-show="overflowing"
        x-on:click="expanded = ! expanded"
        style="display: none;"
        class="mt-0.5 text-xs font-medium text-cyan-600 hover:underline dark:text-cyan-400"
    >
        <span x-show="! expanded">{{ __('More') }}</span>
        <span x-show="expanded" style="display: none;">{{ __('Less') }}</span>
    </button>
</div>
