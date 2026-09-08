{{--
    Global photo lightbox — one instance for the whole app. Self-contained
    (its own x-data, no external JS/store needed) — opened from anywhere by
    dispatching a plain "media-viewer:show" window event, e.g.:

        x-on:click="window.dispatchEvent(new CustomEvent('media-viewer:show', {
            detail: { images: @js($urls), index: {{ $index }} }
        }))"

    A Livewire/Flux quirk in this app means bundled JS registered against
    "alpine:init"/"livewire:init" can lose the race against Alpine's own
    startup (Flux's script tag isn't a module, so it runs synchronously
    during HTML parsing, before any deferred @vite script gets a chance to
    attach a listener) — an Alpine.store() registered that way silently
    never exists. A plain window custom event sidesteps that entirely: it's
    handled by this component's own x-on binding, wired up the same way as
    every other x-data on the page, with no dependency on load order.
--}}
<div
    x-data="{ open: false, images: [], index: 0 }"
    x-on:media-viewer:show.window="images = $event.detail.images; index = $event.detail.index; open = true"
    x-show="open"
    x-on:keydown.escape.window="open = false"
    x-on:keydown.arrow-right.window="if (open && images.length > 1) index = (index + 1) % images.length"
    x-on:keydown.arrow-left.window="if (open && images.length > 1) index = (index - 1 + images.length) % images.length"
    x-transition.opacity
    style="display: none;"
    class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4"
    x-on:click.self="open = false"
    role="dialog"
    aria-modal="true"
    aria-label="{{ __('Photo viewer') }}"
>
    <button
        type="button"
        x-on:click="open = false"
        class="absolute top-4 right-4 text-white/70 hover:text-white"
        aria-label="{{ __('Close') }}"
    >
        <flux:icon.x-mark class="size-7" />
    </button>

    <button
        type="button"
        x-show="images.length > 1"
        x-on:click="index = (index - 1 + images.length) % images.length"
        class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full p-2 text-white/70 hover:bg-white/10 hover:text-white sm:left-4"
        aria-label="{{ __('Previous photo') }}"
    >
        <flux:icon.chevron-left class="size-7" />
    </button>

    <button
        type="button"
        x-show="images.length > 1"
        x-on:click="index = (index + 1) % images.length"
        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full p-2 text-white/70 hover:bg-white/10 hover:text-white sm:right-4"
        aria-label="{{ __('Next photo') }}"
    >
        <flux:icon.chevron-right class="size-7" />
    </button>

    <img
        x-show="images[index]"
        x-bind:src="images[index]"
        x-on:click.stop
        class="max-h-[90vh] max-w-full rounded-lg object-contain select-none"
        alt=""
    >

    <div
        x-show="images.length > 1"
        class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-black/50 px-3 py-1 text-sm text-white"
    >
        <span x-text="index + 1"></span> / <span x-text="images.length"></span>
    </div>
</div>
