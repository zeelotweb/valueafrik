@php
    $media ??= collect();
@endphp

@if ($media->isNotEmpty())
    @php
        $mediaUrls = $media->map->url()->all();
        $visible = $media->take(4);
        $remaining = max(0, $media->count() - 4);
    @endphp

    @if ($media->count() === 1)
        <div class="mt-3" x-data>
            <button
                type="button"
                x-on:click="window.dispatchEvent(new CustomEvent('media-viewer:show', { detail: { images: @js($mediaUrls), index: 0 } }))"
                class="block w-full"
            >
                <img src="{{ $media->first()->url() }}" class="aspect-video w-full rounded-lg object-cover">
            </button>
        </div>
    @else
        <div class="mt-3 grid grid-cols-2 gap-2" x-data>
            @foreach ($visible as $index => $item)
                <button
                    type="button"
                    x-on:click="window.dispatchEvent(new CustomEvent('media-viewer:show', { detail: { images: @js($mediaUrls), index: {{ $index }} } }))"
                    class="relative block"
                >
                    <img src="{{ $item->url() }}" class="aspect-square w-full rounded-lg object-cover">

                    @if ($loop->last && $remaining > 0)
                        <div class="absolute inset-0 flex items-center justify-center rounded-lg bg-black/60 text-lg font-semibold text-white">
                            +{{ $remaining }}
                        </div>
                    @endif
                </button>
            @endforeach
        </div>
    @endif
@endif
