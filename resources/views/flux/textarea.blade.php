@blaze(fold: true, unsafe: [
    // flux:with-field props
    'name', 'label', 'badge',
    'description', 'description:trailing',
    'label:badge', 'label:aside', 'label:trailing',
    'error:name', 'error:bag', 'error:message', 'error:icon', 'error:nested', 'error:deep',
])

@props([
    'name' => $attributes->whereStartsWith('wire:model')->first(),
    'resize' => 'vertical',
    'invalid' => null,
    'rows' => 4,
])

@php
$classes = Flux::classes()
    ->add('block p-3 w-full')
    ->add('shadow-none disabled:shadow-none border-0 rounded-lg')
    // Faded cyan fill, no border — a darker shade of the same fill signals
    // focus/active, and a red fill signals a validation error, matching
    // flux:input for a uniform look across every text field.
    ->add('bg-cyan-50 focus:bg-cyan-100 dark:bg-cyan-950/40 dark:focus:bg-cyan-950/70 dark:disabled:bg-white/[7%]')
    ->add($resize ? match ($resize) {
        'none' => 'resize-none',
        'both' => 'resize',
        'horizontal' => 'resize-x',
        'vertical' => 'resize-y',
        default => 'resize-y',
    } : 'resize-none')
    ->add($rows === 'auto' ? 'field-sizing-content' : '')
    ->add('text-base sm:text-sm text-zinc-700 disabled:text-zinc-500 placeholder-zinc-400 disabled:placeholder-zinc-400/70 dark:text-zinc-300 dark:disabled:text-zinc-400 dark:placeholder-zinc-400 dark:disabled:placeholder-zinc-500')
    ->add('data-invalid:bg-red-50 data-invalid:focus:bg-red-100 dark:data-invalid:bg-red-950/40 dark:data-invalid:focus:bg-red-950/70')
    ;
@endphp

<flux:with-field :$attributes>
    <textarea
        {{ $attributes->class($classes) }}
        rows="{{ $rows }}"
        @isset ($name) name="{{ $name }}" @endisset
        @unblaze(scope: ['name' => $name ?? null, 'invalid' => $invalid ?? false])
        <?php if ($scope['invalid'] || ($scope['name'] && $errors->has($scope['name']))): ?>
        aria-invalid="true" data-invalid
        <?php endif; ?>
        @endunblaze
        data-flux-control
        data-flux-textarea
    >{{ $slot }}</textarea>
</flux:with-field>
