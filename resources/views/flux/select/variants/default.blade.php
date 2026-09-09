@blaze

@props([
    'name' => $attributes->whereStartsWith('wire:model')->first(),
    'placeholder' => null,
    'invalid' => null,
    'size' => null,
])

@php
$invalid ??= ($name && $errors->has($name));

$classes = Flux::classes()
    ->add('appearance-none') // Strip the browser's default <select> styles...
    ->add('[:where(&)]:w-full ps-3 pe-10 block')
    ->add(match ($size) {
        default => 'h-10 py-2 text-base sm:text-sm leading-[1.375rem] rounded-lg',
        'sm' => 'h-8 py-1.5 text-sm leading-[1.125rem] rounded-md',
        'xs' => 'h-6 text-xs leading-[1.125rem] rounded-md',
    })
    ->add('shadow-none disabled:shadow-none')
    // Faded cyan fill, no border — a darker shade of the same fill signals
    // focus/active, matching flux:input/flux:textarea for a uniform look
    // across every field on the platform.
    ->add('bg-cyan-50 focus:bg-cyan-100 dark:bg-cyan-950/40 dark:focus:bg-cyan-950/70 dark:disabled:bg-white/[7%]')
    ->add('text-zinc-700 dark:text-zinc-300 disabled:text-zinc-500 dark:disabled:text-zinc-400')
    // Make the placeholder match the text color of standard input placeholders...
    ->add('has-[option.placeholder:checked]:text-zinc-400 dark:has-[option.placeholder:checked]:text-zinc-400')
    // Options on Windows don't inherit dark mode styles, so we need to force them...
    // Matched as descendants rather than children so options inside an <optgroup> are covered too...
    ->add('dark:[&_option]:bg-zinc-700 dark:[&_option]:text-white')
    ->add($invalid
        ? 'bg-red-50 focus:bg-red-100 dark:bg-red-950/40 dark:focus:bg-red-950/70'
        : ''
    )
    ;
@endphp

<select
    {{ $attributes->class($classes) }}
    @if ($invalid) aria-invalid="true" data-invalid @endif
    @isset ($name) name="{{ $name }}" @endisset
    @if (is_numeric($size)) size="{{ $size }}" @endif
    data-flux-control
    data-flux-select-native
    data-flux-group-target
>
    <?php if ($placeholder): ?>
        <option value="" disabled selected class="placeholder">{{ $placeholder }}</option>
    <?php endif; ?>

    {{ $slot }}
</select>
