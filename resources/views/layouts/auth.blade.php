<x-layouts::auth.simple :title="$title ?? null">
    {{ $slot }}
    @include('partials.marketing-footer')
</x-layouts::auth.simple>
