@php
    $photos ??= [];
    $max ??= 4;
    $atLimit = count($photos) >= $max;
@endphp

<div
    x-data="{ uploading: false, progress: 0 }"
    x-on:livewire-upload-start="uploading = true; progress = 0"
    x-on:livewire-upload-finish="uploading = false"
    x-on:livewire-upload-cancel="uploading = false"
    x-on:livewire-upload-error="uploading = false"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
>
    @if (count($photos) > 0)
        <div class="mt-3 grid grid-cols-4 gap-2">
            @foreach ($photos as $index => $photo)
                <div class="group relative aspect-square overflow-hidden rounded-lg bg-stone-100 dark:bg-stone-800" wire:key="{{ $property }}-preview-{{ $index }}">
                    <img src="{{ $photo->temporaryUrl() }}" class="size-full object-cover">
                    <button
                        type="button"
                        wire:click="{{ $removeMethod }}({{ $index }})"
                        class="absolute top-1 end-1 flex size-5 items-center justify-center rounded-full bg-black/60 text-white hover:bg-black/80"
                    >
                        <flux:icon.x-mark class="size-3" />
                    </button>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-3 flex flex-wrap items-center gap-3">
        <label
            @class([
                'inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-dashed border-stone-300 px-3 py-1.5 text-sm font-medium text-stone-600 transition hover:border-green-600 hover:text-green-600 dark:border-stone-700 dark:text-stone-400 dark:hover:border-green-500 dark:hover:text-green-400',
                'pointer-events-none opacity-50' => $atLimit,
            ])
        >
            <flux:icon.photo class="size-4" />
            {{ count($photos) > 0 ? __('Add more') : __('Add photos') }}
            <input
                type="file"
                wire:model="{{ $property }}"
                multiple
                accept="image/*"
                class="hidden"
                @disabled($atLimit)
            >
        </label>

        <span x-show="uploading" style="display: none;" class="flex items-center gap-1.5 text-sm text-green-600 dark:text-green-400">
            <flux:icon.loading variant="micro" class="size-3.5" />
            <span x-text="{{ Js::from(__('Uploading…')) }} + ' ' + progress + '%'"></span>
        </span>
    </div>

    <p class="mt-1 text-xs text-stone-400 dark:text-stone-500">
        {{ trans_choice('Up to :max photo, :size MB each.|Up to :max photos, :size MB each.', $max, ['max' => $max, 'size' => $maxSizeMb ?? 8]) }}
    </p>

    @error($property) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    @error($property.'.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
